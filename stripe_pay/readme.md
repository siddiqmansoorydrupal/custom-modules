### Available hooks on this module
```php
/**
 * Implements hook_stripe_pay_success_message().
 */
function stripe_pay_stripe_pay_success_message(&$message)
{
    // Modify the success message.
    // $message = 'Success';
}
/**
 * Implements hook_stripe_pay_cancel_message().
 */
function stripe_pay_stripe_pay_cancel_message(&$message)
{
    // Modify the cancel message.
    // $message = 'canceled';
}
/**
 * Implements hook_stripe_pay_success_redirect().
 */
function stripe_pay_stripe_pay_success_redirect(&$redirect_url)
{
    // Modify the success redirect.
    // $redirect_url = ''; //full url
}
/**
 * Implements hook_stripe_pay_cancel_redirect().
 */
function stripe_pay_stripe_pay_cancel_redirect(&$redirect_url)
{
    // Modify the cancel redirect.
    // $redirect_url = ''; //full url
}
```
