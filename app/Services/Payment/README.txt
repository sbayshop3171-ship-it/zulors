A small guide to add a new payment provider.
Actual at 01 May, 2025.

1. Create a new driver class in the `Drivers` folder.
	Based on the provider name, create a new class in the `Drivers` folder.
	Example: HrenPayDriver.php. 
	The driver class should implement the `PaymentGatewayInterface`.
	And should return a `PaymentIntentResult` object.

2. Add new provider to the `PaymentGatewayFactory` class.
	Add it to the `make` method and return the new driver class.

4. Add provider logo to the `public/assets/payments` folder.
	And add the relative path to the `logo` field in the `config/payment.php` file.

5. Add create a new webhook controller in the `Http/Controllers/Webhook/Payment` folder.
	Based on the provider name, create a new class in the `Webhook` folder.
	Example: HrenPayWebhookController.php.
	The webhook controller should extend the `Controller` class and have a `handleWebhook` method.

	Inside the `handleWebhook` method, you should:
	1. Validate the webhook request.
	2. Get the payment reference id from the request.
	3. Get the payment handler from the `PaymentProcessService` class.
	4. Call the appropriate method on the payment handler based on the webhook event type.

6. Add the webhook route to the `routes/webhooks/payment_webhooks.php` file.
	Example: 
	Route::post('/payment/hrenpay/webhook', [HrenPayWebhookController::class, 'handleWebhook']);

