# Brevo Email Setup

Use Brevo for outgoing transactional mail such as signup verification, password reset, email-change OTP codes, and notification email. Use Cloudflare Email Routing separately for receiving and forwarding inbound mail.

## Brevo Dashboard

1. Create or sign in to a Brevo account.
2. Add and authenticate `zulors.com` in Brevo sender/domain settings.
3. Copy Brevo SPF, DKIM, and DMARC DNS records into Cloudflare DNS.
4. Wait until Brevo marks the domain as authenticated.
5. For API mode, create an API key from `SMTP & API > API keys & MCP`.
6. For SMTP fallback, create an SMTP key from `SMTP & API > SMTP`.
7. Verify a sender address such as `noreply@zulors.com`.

## Recommended: Brevo API Mode

Open `Admin Panel > Settings > E-Mail (SMTP) Settings`, click `Use Brevo API preset`, then use:

```text
Email transport: Brevo API
Brevo API key: Brevo API key that starts with xkeysib-
Server Host: api.brevo.com
Local domain: zulors.com
Port: 443
Timeout: 60
Encryption: tls
From address: noreply@zulors.com
From name: Zulors
```

API mode sends through Brevo's transactional email API and does not need Brevo SMTP IP allowlisting.

## SMTP Fallback

Open `Admin Panel > Settings > E-Mail (SMTP) Settings` and use:

```text
Email transport: smtp
Server Host: smtp-relay.brevo.com
Local domain: zulors.com
Port: 587
Timeout: 60
Username: Brevo SMTP login
Password: Brevo SMTP key
Encryption: tls
From address: noreply@zulors.com
From name: Zulors
```

Click `Use Brevo SMTP preset` to fill the safe non-secret values automatically. Paste the Brevo SMTP login and SMTP key manually, then save.

## Testing

Open the `Email (SMTP) Testing` tab and send a test email to a Gmail or Outlook address. If delivery fails, check:

- The Brevo domain authentication status.
- The sender address verification status.
- For API mode, that the API key starts with `xkeysib-` and is active.
- For SMTP mode, that the password is the Brevo SMTP key, not the normal account password.
- That Cloudflare DNS contains the Brevo SPF/DKIM/DMARC records.

## Official Documentation

- Brevo transactional email API: https://developers.brevo.com/reference/send-transac-email
- Brevo API keys: https://help.brevo.com/hc/en-us/articles/209467485-Create-or-delete-an-API-key
- Brevo SMTP: https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP
- Brevo domain authentication: https://help.brevo.com/hc/en-us/articles/12163873383186-Authenticate-your-domain-with-Brevo-Brevo-code-DKIM-record-DMARC-record
- Brevo free plan limits: https://help.brevo.com/hc/en-us/articles/208580669-FAQs-What-are-the-limits-of-the-Free-plan
