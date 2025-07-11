<html lang="en">
    <head>
        <title>{{ trans('cashier::messages.paystack') }}</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
        <script type="text/javascript" src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
        <link rel="stylesheet" href="{{ \MailStok\Cashier\Cashier::public_url('/vendor/acelle-cashier/css/main.css') }}">
    </head>
    
    <body>
        <div class="main-container row mt-40">
            <div class="col-md-2"></div>
            <div class="col-md-4 mt-40 pd-60">
                <label class="text-semibold text-muted mb-20 mt-0">
                    <strong>
                        {{ trans('cashier::messages.paystack') }}
                    </strong>
                </label>
                <img class="rounded" width="100%" src="{{ \MailStok\Cashier\Cashier::public_url('/vendor/acelle-cashier/image/paystack.svg') }}" />
            </div>
            <div class="col-md-4 mt-40 pd-60">  
                <h2 class="mb-3">{!! $invoice->title !!}</h2>              
                <label>{!! $invoice->description !!}</label>  
                <hr>
                
                
                <p>{!! trans('cashier::messages.paystack.click_bellow_to_pay', [
                    'price' => $invoice->formattedTotal(),
                ]) !!}</p>

                <form id="paymentForm">
                    <a href="javascript:;" class="btn btn-secondary full-width" onclick="payWithPaystack()">
                        {{ trans('cashier::messages.paystack.pay') }}
                    </a>
                </form>
                <script src="https://js.paystack.co/v1/inline.js"></script> 

                <form id="checkoutForm" method="POST" action="{{ \MailStok\Cashier\Cashier::lr_action('\MailStok\Cashier\Controllers\PaystackController@checkout', [
                    'invoice_uid' => $invoice->uid
                ]) }}">
                    {{ csrf_field() }}
                    <input type="hidden" name="reference" value="" />
                </form>
                
                <script>
                    var paymentForm = document.getElementById('paymentForm');
                    paymentForm.addEventListener('submit', payWithPaystack, false);
                    function payWithPaystack() {
                        var handler = PaystackPop.setup({
                            key: '{{ $service->publicKey }}', // Replace with your public key
                            email: '{{ $invoice->customer->user->email }}',
                            amount: {{ $invoice->total() }} * 100, // the amount value is multiplied by 100 to convert to the lowest currency unit
                            currency: '{{ $invoice->getCurrencyCode() }}', // Use GHS for Ghana Cedis or USD for US Dollars
                            firstname: '{{ $invoice->billing_first_name }}',
                            lastname: '{{ $invoice->billing_last_name }}',
                            phone: '{{ $invoice->billing_phone }}',
                            reference: ''+Math.floor((Math.random() * 1000000000) + 1), // Replace with a reference you generated
                            callback: function(response) {
                                var reference = response.reference;
                                
                                $('[name="reference"]').val(reference);
                                $('#checkoutForm').submit();
                            },
                            onClose: function() {
                                alert('Transaction was not completed, window closed.');
                            },
                        });
                        handler.openIframe();
                    }
                </script>

                <div class="my-4">
                    <hr>
                    <form id="cancelForm" method="POST" action="{{ action('SubscriptionController@cancelInvoice', [
                                'invoice_uid' => $invoice->uid,
                    ]) }}">
                        {{ csrf_field() }}
                        <a href="javascript:;" onclick="$('#cancelForm').submit()">
                            {{ trans('messages.subscription.cancel_now_change_other_plan') }}
                        </a>
                    </form>
                    
                </div>
            </div>
            <div class="col-md-2"></div>
            <div class="col-md-4">
               
            </div>
        </div>
        <br />
        <br />
        <br />
    </body>
</html>