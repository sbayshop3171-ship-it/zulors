{{-- Add on error fallback by setting to null, to prevent infinite loop of onerror events --}}
<img {{ $attributes }} onerror="this.onerror=null; this.src='{{ asset(config('user.avatar')) }}';">