<?php

namespace App\Services;

use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Server-side library encryption, modelled after Seafile's encrypted libraries.
 *
 * Each encrypted library has a random 256-bit key. That key is sealed with a key
 * derived (Argon2id) from the user's password. Users "unlock" a library for a
 * session by supplying the password; the plain key is then kept in the cache for
 * a limited time and used to encrypt/decrypt file blobs with XChaCha20-Poly1305
 * secretstream (chunked, so large files stream without being fully loaded).
 */
class LibraryCrypto
{
    public const CHUNK = 1024 * 1024;

    public const UNLOCK_TTL_MINUTES = 60;

    public function setupLibrary(Library $library, string $password): void
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $libraryKey = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $kek = $this->deriveKey($password, $salt);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $sealed = sodium_crypto_secretbox($libraryKey, $nonce, $kek);

        $library->is_encrypted = true;
        $library->password_hash = password_hash($password, PASSWORD_ARGON2ID);
        $library->encrypted_key = base64_encode($nonce.$sealed);
        $library->key_salt = base64_encode($salt);
        sodium_memzero($kek);
        sodium_memzero($libraryKey);
    }

    public function changePassword(Library $library, string $current, string $new): void
    {
        $key = $this->openKey($library, $current);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $kek = $this->deriveKey($new, $salt);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $library->password_hash = password_hash($new, PASSWORD_ARGON2ID);
        $library->encrypted_key = base64_encode($nonce.sodium_crypto_secretbox($key, $nonce, $kek));
        $library->key_salt = base64_encode($salt);
        $library->save();
        sodium_memzero($kek);
        sodium_memzero($key);
    }

    /** Verify the password and cache the library key for the user. */
    public function unlock(Library $library, User $user, string $password): void
    {
        $key = $this->openKey($library, $password);
        Cache::put($this->cacheKey($library, $user), base64_encode($key), now()->addMinutes(self::UNLOCK_TTL_MINUTES));
        sodium_memzero($key);
    }

    public function lock(Library $library, User $user): void
    {
        Cache::forget($this->cacheKey($library, $user));
    }

    public function isUnlocked(Library $library, User $user): bool
    {
        return ! $library->is_encrypted || Cache::has($this->cacheKey($library, $user));
    }

    /** Get the plain library key for an unlocked library, or throw a 423 (Locked). */
    public function keyFor(Library $library, ?User $user): string
    {
        $cached = $user ? Cache::get($this->cacheKey($library, $user)) : null;
        abort_if($cached === null, 423, 'This library is encrypted. Unlock it with its password first.');

        return base64_decode($cached);
    }

    // ----- streaming file encryption -------------------------------------

    /** Encrypt the given file path into a new temp file. Returns the temp path. */
    public function encryptFile(string $sourcePath, string $key): string
    {
        $out = tempnam(sys_get_temp_dir(), 'enc');
        $in = fopen($sourcePath, 'rb');
        $o = fopen($out, 'wb');
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        fwrite($o, $header);
        while (! feof($in)) {
            $chunk = fread($in, self::CHUNK);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $tag = feof($in) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            fwrite($o, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $tag));
        }
        fclose($in);
        fclose($o);

        return $out;
    }

    /** Stream-decrypt a readable stream to the output (echo). */
    public function decryptStreamToOutput($stream, string $key): void
    {
        $header = fread($stream, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        $chunkSize = self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        while (! feof($stream)) {
            $chunk = fread($stream, $chunkSize);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
            if ($res === false) {
                throw new \RuntimeException('Decryption failed.');
            }
            echo $res[0];
            flush();
        }
    }

    public function decryptToString($stream, string $key): string
    {
        ob_start();
        $this->decryptStreamToOutput($stream, $key);

        return ob_get_clean();
    }

    // ----------------------------------------------------------------------

    protected function openKey(Library $library, string $password): string
    {
        if (! $library->is_encrypted || ! $library->encrypted_key) {
            throw ValidationException::withMessages(['password' => 'This library is not encrypted.']);
        }
        if (! password_verify($password, $library->password_hash)) {
            throw ValidationException::withMessages(['password' => 'Wrong library password.']);
        }
        $blob = base64_decode($library->encrypted_key);
        $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $sealed = substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $kek = $this->deriveKey($password, base64_decode($library->key_salt));
        $key = sodium_crypto_secretbox_open($sealed, $nonce, $kek);
        sodium_memzero($kek);
        if ($key === false) {
            throw ValidationException::withMessages(['password' => 'Could not unseal the library key.']);
        }

        return $key;
    }

    protected function deriveKey(string $password, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    protected function cacheKey(Library $library, User $user): string
    {
        return "libkey:{$library->id}:{$user->id}";
    }
}
