# Melipayamak — SendByBaseNumber API Reference

**Source:** Official Melipayamak API documentation provided by the project owner
**API Method:** `SendByBaseNumber`
**Protocol:** SOAP Web Service
**Encoding:** UTF-8

---

## 1. Web Service Address

### Non-WSDL

```text
http://api.payamak-panel.com/post/send.asmx
```

### WSDL

```text
http://api.payamak-panel.com/post/send.asmx?wsdl
```

For PHP `SoapClient`, use the WSDL endpoint:

```text
http://api.payamak-panel.com/post/Send.asmx?wsdl
```

---

# 2. SendByBaseNumber

The `SendByBaseNumber` method is used to send SMS messages using a predefined message template through the **shared service line**.

The shared service line supports sending messages to mobile numbers that may be included in the telecommunications blacklist.

The predefined message/template must be configured and approved by the Melipayamak system administrator.

---

# 3. Input Parameters

The method accepts the following parameters:

| Parameter  | Type     | Description                                                                                                          |
| ---------- | -------- | -------------------------------------------------------------------------------------------------------------------- |
| `username` | String   | Username of the account in the Melipayamak system                                                                    |
| `password` | String   | Password of the account in the Melipayamak system                                                                    |
| `text`     | String[] | Variables configured in the predefined message/template. Variables must be sent as an array and in the correct order |
| `to`       | String   | Recipient mobile number. Only one mobile number can be provided                                                      |
| `bodyId`   | Int      | ID of the predefined message/template that has been approved by the system administrator                             |

---

# 4. `text` Parameter

The `text` parameter is an array.

The values inside this array correspond to the variables configured in the predefined message/template.

The variables must be sent:

1. As an array
2. In the same order as defined in the predefined message
3. With values compatible with the predefined message

Example:

```php
"text" => array("arg1", "arg2")
```

The number and order of variables must match the configured predefined message.

---

# 5. `to` Parameter

The `to` parameter contains the recipient's mobile number.

Only **one mobile number** can be sent in each `SendByBaseNumber` request.

Example:

```text
09123456789
```

Multiple recipient numbers are not supported by this method.

---

# 6. `bodyId` Parameter

`bodyId` is the ID of the predefined message/template.

The predefined message must be approved by the Melipayamak system administrator.

Example:

```php
"bodyId" => 123
```

The actual `bodyId` must be replaced with the ID assigned to the approved predefined message.

---

# 7. PHP Example — Using Melipayamak API

The official documentation provides the following GitHub-package style example:

```php
$username = 'username';
$password = 'password';

$api = new MelipayamakApi($username, $password);

$smsSoap = $api->sms('soap');

$to = '09123456789';

$smsSoap->sendByBaseNumber($text, $to, $bodyId);
```

---

# 8. PHP Example — Procedural PHP Without GitHub Package

The official documentation also provides a direct PHP `SoapClient` implementation:

```php
ini_set("soap.wsdl_cache_enabled", "0");

$sms = new SoapClient(
    "http://api.payamak-panel.com/post/Send.asmx?wsdl",
    array(
        "encoding" => "UTF-8"
    )
);

$data = array(
    "username" => "",
    "password" => "",
    "text" => array("arg1", "arg2"),
    "to" => "",
    "bodyId" => 0
);

$send_Result = $sms->SendByBaseNumber($data)->SendByBaseNumberResult;

echo $send_Result;
```

The actual values for:

```text
username
password
text
to
bodyId
```

must be supplied according to the actual Melipayamak account and approved predefined message.

---

# 9. SOAP Request/Response

The service returns a SOAP response.

Expected HTTP response structure:

```text
HTTP/1.1 200 OK
Content-Type: text/xml; charset=utf-8
Content-Length: length
```

SOAP structure:

```xml
<soap:Envelope
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">

    <soap:Body>
        string
    </soap:Body>

</soap:Envelope>
```

The actual result is returned as the `SendByBaseNumberResult` value.

---

# 10. Return Value

The method returns:

```text
ReturnValue
```

with type:

```text
String
```

The returned string can contain a successful `recId` or one of the documented status/error codes.

---

# 11. Successful Response

According to the provided Melipayamak documentation:

If the returned value is:

```text
recId
```

and it is a number with **more than 15 digits**, it indicates successful SMS submission.

The `recId` is a unique number associated with the submitted SMS.

The documentation also states that this ID can be used for delivery reporting through methods such as:

```text
GetDeliveries
```

---

# 12. Return Codes

The following return values are documented for `SendByBaseNumber`.

| Return Value | Description                                                                      |
| ------------ | -------------------------------------------------------------------------------- |
| `110-`       | API Key must be used instead of password                                         |
| `109-`       | A permitted IP must be configured for API usage                                  |
| `108-`       | IP has been blocked due to unsuccessful API authentication attempts              |
| `-10`        | One of the submitted variables contains a link                                   |
| `-7`         | Error with the sender number; contact support                                    |
| `-6`         | Internal error; contact support                                                  |
| `-5`         | Submitted text does not match the variables configured in the predefined message |
| `-4`         | The submitted predefined-message code is invalid or has not been approved        |
| `-3`         | The sender line is not configured in the system; contact support                 |
| `-2`         | Recipient limit exceeded; this method allows only one mobile number per request  |
| `-1`         | Access to this web service is disabled; contact support                          |
| `0`          | Username or password is incorrect                                                |
| `2`          | Insufficient account credit                                                      |
| `6`          | System is being updated                                                          |
| `7`          | Message contains a filtered word; contact the administrative department          |
| `10`         | User account is not active                                                       |
| `11`         | Message was not sent                                                             |
| `12`         | User's required documents are incomplete                                         |
| `16`         | No recipient number was found                                                    |
| `17`         | SMS text is empty                                                                |
| `18`         | Recipient number is invalid                                                      |
| `19`         | Hourly sending limit has been exceeded                                           |

---

# 13. Important API Behavior

The following behavior is explicitly defined in the provided documentation:

### Recipient limitation

Each request can contain only one mobile number.

```text
1 request = 1 recipient
```

### Predefined message

The SMS must use a predefined message identified by:

```text
bodyId
```

### Variables

Template variables are passed through:

```text
text[]
```

and must be supplied in the configured order.

### Successful submission

A `recId` consisting of a number with more than 15 digits indicates successful submission.

### Delivery report

The returned `recId` can be used with a delivery-report method such as:

```text
GetDeliveries
```

---

# 14. API Integration Contract

When implementing `SendByBaseNumber`, use exactly the following contract:

```text
Endpoint:
http://api.payamak-panel.com/post/Send.asmx?wsdl

Method:
SendByBaseNumber

Parameters:
username
password
text[]
to
bodyId

Return:
SendByBaseNumberResult
```

Do not change parameter names.

Do not send multiple recipients.

Do not replace `bodyId` with message text.

Do not send template variables outside the `text[]` parameter.

---

# 15. Implementation Reference for Copilot Agent

This document is a direct technical reference for the `SendByBaseNumber` API.

When implementing the integration:

* Use the documented WSDL endpoint.
* Use PHP `SoapClient` or the documented Melipayamak PHP package approach.
* Use the exact `SendByBaseNumber` method.
* Use the exact documented parameter names.
* Send template variables through `text[]`.
* Send one recipient through `to`.
* Use the approved template ID through `bodyId`.
* Read `SendByBaseNumberResult`.
* Handle the documented return values.
* Treat a numeric `recId` with more than 15 digits as successful submission according to the provider documentation.

Do not invent undocumented endpoints, parameters, return values, or API behavior.

---

# 16. Official API Reference

WSDL:

```text
http://api.payamak-panel.com/post/Send.asmx?wsdl
```

Non-WSDL:

```text
http://api.payamak-panel.com/post/send.asmx
```

API Method:

```text
SendByBaseNumber
```

---

## End of API Reference