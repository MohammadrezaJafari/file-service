<div style="display:flex;align-items:center;gap:.6rem">
    <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#8b5cf6);color:#fff;box-shadow:0 6px 16px rgba(37,99,235,.35)">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>
    </span>
    <span style="font-weight:700;font-size:1.05rem">{{ \App\Models\Setting::get('site_name', 'File Service') }} <span style="font-weight:400;color:#64748b">· {{ __('Admin') }}</span></span>
</div>
