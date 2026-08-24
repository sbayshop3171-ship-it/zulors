@props([
    'title' => ''
])

<div class="auth-form-header block text-center">
    @if (isset($icon))
        <div class="flex justify-center">
            <div class="auth-form-header__icon size-8 overflow-hidden text-lab-pr2 mb-2">
                {!! $icon !!}
            </div>
        </div>
    @endif

    <h1 class="auth-form-header__title text-title-2 text-lab-pr2 font-semibold">
        {!! $title !!}
    </h1>

    @if(isset($caption))
        <p class="auth-form-header__caption text-par-m text-lab-sc">
            {!! $caption !!}
        </p>
    @endif
</div>
