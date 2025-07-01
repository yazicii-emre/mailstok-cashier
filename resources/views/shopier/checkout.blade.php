@php
    \Acelle\Cashier\Controllers\ShopierController::payWithShopier($invoice) 

@endphp


{{-- @extends('layouts.core.frontend_dark', [
    'subscriptionPage' => true,
])

@section('title', trans('messages.subscriptions'))

@section('menu_title')
    @include('subscription._title')
@endsection

@section('menu_right')
    @include('layouts.core._top_activity_log')
    @include('layouts.core._menu_frontend_user', [
        'menu' => 'subscription',
    ])
@endsection

@section('content')
    <div class="container mt-5 mb-0">
        <div class="row">
            <div class="col-md-7">
                <h2><i class="fas fa-credit-card me-2"></i>{!! trans('cashier::messages.pay_invoice') !!}</h2>
                <hr>

                {{ \Acelle\Cashier\Controllers\ShopierController::payWithShopier($invoice) }}
                <hr>

            </div>
            <div class="col-md-5">
                <div class="card shadow-sm rounded-3 px-2 py-2 mb-4">
                    <div class="card-body p-4">

                        @include('invoices.bill', [
                            'bill' => $invoice->getBillingInfo(),
                        
                            // 'bill' => $invoice->mapType()->stupidFunct('shopier'),
                        ])

                        <div class="d-flex align-items-center mt-4 justify-content-end">

                            <form id="cancelForm" method="POST"
                                action="{{ action('SubscriptionController@cancelInvoice', [
                                    'invoice_uid' => $invoice->uid,
                                ]) }}">
                                {{ csrf_field() }}
                                <a style="border-radius:10px;" class="btn btn-danger" href="{{ Billing::getReturnUrl() }}">

                                    <i class="fa fa-arrow-left"></i>

                                    {{ trans('cashier::messages.go_back') }}
                                </a>
                            </form>
                        </div>


                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection --}}
