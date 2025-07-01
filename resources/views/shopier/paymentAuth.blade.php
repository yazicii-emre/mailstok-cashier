<html lang="en">
<head>
    <title>{{ trans('cashier::messages.shopier') }}</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <link rel="stylesheet" href="{{ \Acelle\Cashier\Cashier::public_url('/vendor/acelle-cashier/css/main.css') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    @include('layouts.core._includes')

    @include('layouts.core._script_vars')
</head>

<body>
<script>
    addMaskLoading(`{!! trans('cashier::messages.shopier.checkout.processing_payment.intro') !!}`);

    // Placeholder function to handle payment redirect to Shopier
    function redirectToShopier() {
        $.ajax({
            url: '{{ action("\Acelle\Cashier\Controllers\ShopierController@checkout", ['invoice_uid' => $invoice->uid]) }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
            },
            success: function(response) {
                if (response.status === 'success') {
                    // Redirect to Shopier’s payment URL
                    window.location.href = response.payment_url;
                } else {
                    removeMaskLoading();
                    new Dialog('alert', {
                        message: response.message || '{{ trans("cashier::messages.shopier.payment_failed") }}',
                        ok: function() {
                            window.location = '{{ Billing::getReturnUrl() }}';
                        }
                    });
                }
            },
            error: function() {
                removeMaskLoading();
                new Dialog('alert', {
                    message: '{{ trans("cashier::messages.shopier.payment_error") }}',
                    ok: function() {
                        window.location = '{{ Billing::getReturnUrl() }}';
                    }
                });
            }
        });
    }

    // Initiate redirect to Shopier payment
    redirectToShopier();
</script>
</body>
</html>
