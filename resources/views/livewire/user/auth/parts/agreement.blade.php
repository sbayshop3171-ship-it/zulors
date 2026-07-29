<div class="block">
    <p class="text-par-s text-lab-sc text-center">
        {!! __('auth.auth_agreement', [
            'app_name' => config('app.name'),
            'terms_link' => route('document.terms.index'),
            'policy_link' => route('document.privacy.index')
        ]) !!}
    </p>
</div>