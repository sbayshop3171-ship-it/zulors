<x-card>
	<div class="p-4">
		<h4 class="text-par-l font-semibold mb-1 text-lab-pr2">
			{{ __('admin/info.smtp_solutions.title') }}
		</h4>

		<p class="text-par-m text-lab-sc mb-4">
			{{ __('admin/info.smtp_solutions.brevo_intro') }}
		</p>

        <div class="space-y-3 text-par-m text-lab-sc">
            <p>{!! __('admin/info.smtp_solutions.step_domain') !!}</p>
            <p>{!! __('admin/info.smtp_solutions.step_dns') !!}</p>
            <p>{!! __('admin/info.smtp_solutions.step_smtp_key') !!}</p>
            <p>{!! __('admin/info.smtp_solutions.step_sender') !!}</p>
            <p>{!! __('admin/info.smtp_solutions.step_test') !!}</p>
        </div>

        <div class="mt-5 rounded-xl bg-fill-tr p-4 text-par-s text-lab-pr">
            <div class="grid gap-2">
                <div><strong>{{ __('admin/info.smtp_solutions.values.transport') }}:</strong> Brevo API</div>
                <div><strong>{{ __('admin/config.form.brevo_api_key') }}:</strong> xkeysib-...</div>
                <div><strong>{{ __('admin/info.smtp_solutions.values.transport') }} SMTP fallback:</strong> smtp</div>
                <div><strong>{{ __('admin/info.smtp_solutions.values.host') }}:</strong> smtp-relay.brevo.com</div>
                <div><strong>{{ __('admin/info.smtp_solutions.values.port') }}:</strong> 587</div>
                <div><strong>{{ __('admin/info.smtp_solutions.values.encryption') }}:</strong> tls</div>
                <div><strong>{{ __('admin/info.smtp_solutions.values.from_address') }}:</strong> noreply@zulors.com</div>
            </div>
        </div>

        <div class="mt-5 grid gap-2">
            <a href="https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP" class="block" target="_blank">
                <x-ui.buttons.pill size="xs" type="button" width="w-full" btnText="{{ __('admin/info.smtp_solutions.links.smtp') }}"></x-ui.buttons.pill>
            </a>
            <a href="https://help.brevo.com/hc/en-us/articles/12163873383186-Authenticate-your-domain-with-Brevo-Brevo-code-DKIM-record-DMARC-record" class="block" target="_blank">
                <x-ui.buttons.pill size="xs" type="button" width="w-full" variant="outline" btnText="{{ __('admin/info.smtp_solutions.links.domain') }}"></x-ui.buttons.pill>
            </a>
            <a href="https://help.brevo.com/hc/en-us/articles/208580669-FAQs-What-are-the-limits-of-the-Free-plan" class="block" target="_blank">
                <x-ui.buttons.pill size="xs" type="button" width="w-full" variant="link" btnText="{{ __('admin/info.smtp_solutions.links.free_plan') }}"></x-ui.buttons.pill>
            </a>
        </div>
	</div>
</x-card>
