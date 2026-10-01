# Website lead capture

Sign-Forge accepts enquiries at:

`POST {your-sign-forge-url}/api/public/leads`

Use the same host you already use for Sign-Forge. Do not hard-code a production domain in the website if the host can change.

The request body is JSON, at most 20 KB. There is no staff session and no CSRF token. A honeypot, a minimum time on the form, and an hourly limit per address replace that.

## Required

- `name`
- `message`
- `form_started_at` — Unix time when the form was rendered. The default minimum is 3 seconds.

## Optional

- `company_name`
- `phone`
- `email`
- `service_interest`
- `campaign_code` — must match a campaign code already stored in Sign-Forge
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`
- `landing_page`
- `referrer`

## Honeypot

Include an input named `company_website` and hide it with CSS. People leave it empty. If it has a value, Sign-Forge answers as if the enquiry was received and does not store a lead.

## Success

```json
{"ok": true, "received": true}
```

A problem looks like:

```json
{"ok": false, "received": false, "message": "Please try again later."}
```

The message is safe to show. It does not include a database error or an internal id.

## PHP

```php
$payload = json_encode([
    'name' => $_POST['name'] ?? '',
    'email' => $_POST['email'] ?? '',
    'phone' => $_POST['phone'] ?? '',
    'company_name' => $_POST['company'] ?? '',
    'service_interest' => $_POST['service'] ?? '',
    'message' => $_POST['message'] ?? '',
    'company_website' => $_POST['company_website'] ?? '',
    'form_started_at' => (int) ($_POST['form_started_at'] ?? 0),
    'utm_source' => $_GET['utm_source'] ?? '',
    'utm_campaign' => $_GET['utm_campaign'] ?? '',
    'landing_page' => $_SERVER['REQUEST_URI'] ?? '',
    'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
]);
$ch = curl_init(getenv('SIGNFORGE_URL') . '/api/public/leads');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
```

## JavaScript

```javascript
await fetch(signForgeUrl + '/api/public/leads', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    name: form.name.value,
    email: form.email.value,
    phone: form.phone.value,
    message: form.message.value,
    company_website: form.company_website.value,
    form_started_at: Number(form.form_started_at.value),
    utm_source: new URLSearchParams(location.search).get('utm_source'),
    utm_campaign: new URLSearchParams(location.search).get('utm_campaign'),
    landing_page: location.pathname,
    referrer: document.referrer
  })
});
```

Put `form_started_at` in a hidden field when the page loads: `Math.floor(Date.now() / 1000)`.
