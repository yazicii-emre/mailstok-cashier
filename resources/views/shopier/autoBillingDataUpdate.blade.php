<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ trans('cashier::messages.shopier') }}</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <link rel="stylesheet" href="{{ \Acelle\Cashier\Cashier::public_url('/vendor/acelle-cashier/css/main.css') }}">
</head>

<body>
<div class="main-container row mt-40">
    <div class="col-md-2"></div>
    <div class="col-md-4 mt-40 pd-60">
        <label class="text-semibold text-muted mb-20 mt-0">
            <strong>{{ trans('cashier::messages.shopier') }}</strong>
        </label>
        <img class="rounded" width="100%" src="{{ \Acelle\Cashier\Cashier::public_url('/vendor/acelle-cashier/image/shopier.png') }}" />
    </div>
    <div class="col-md-4 mt-40 pd-60">
        <div class="sub-section">
            <h4 class="text-semibold mb-3 mt-4">{!! trans('cashier::messages.shopier.checkout_description') !!}</h4>

            <form id="shopier_button" action="{{ \Acelle\Cashier\Cashier::lr_action('\App\Http\Controllers\ShopierController@redirectToShopier') }}" method="POST">
                {{ csrf_field() }}

                <!-- Shopier için gereken bilgileri gönderiyoruz -->
                <input type="hidden" name="invoice_id" value="{{ $invoice->uid }}" />
                <input type="hidden" name="total" value="{{ $invoice->total }}" />
                <input type="hidden" name="currency" value="TRY" />
                <input type="hidden" name="return_url" value="{{ request()->return_url }}" />

                <button type="submit" class="btn btn-primary">
                    {{ trans('cashier::messages.shopier.proceed_to_payment') }}
                </button>
            </form>
        </div>

        <a href="{{ Billing::getReturnUrl() }}" class="text-muted mt-4" style="text-decoration: underline; display: block">
            {{ trans('cashier::messages.shopier.return_back') }}
        </a>
    </div>
    <div class="col-md-2"></div>
</div>

<script>
    // İlgili işlemler veya ekstra scriptler için bu bölümü kullanabilirsiniz.
</script>
</body>
</html>
