<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        if (! (bool) Setting::get('registration_enabled', '1')) {
            abort(403, 'Registration is disabled.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data);
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return response()->json(['token' => $token, 'user' => UserResource::private($user)], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['This account has been deactivated.']]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($data['device_name'] ?? 'web')->plainTextToken;

        return response()->json(['token' => $token, 'user' => UserResource::private($user)]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }
        unset($data['avatar']);

        $user->fill($data)->save();

        return new UserResource($user);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->noContent();
    }

    public function settings()
    {
        return response()->json([
            'site_name' => Setting::get('site_name', 'File Service'),
            'registration_enabled' => (bool) Setting::get('registration_enabled', '1'),
            'share_links_enabled' => (bool) Setting::get('share_links_enabled', '1'),
            'max_upload_bytes' => (int) Setting::get('max_upload_bytes', 0),
        ]);
    }
}
