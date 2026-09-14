<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\EntitlementRepository;
use App\Repositories\PaymentRepository;
use App\Services\EntitlementResolver;
use App\Services\MeteringService;
use App\Services\Payments\GatewayFactory;
use App\Support\Auth;
use App\Support\BillingConfig;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;
use InvalidArgumentException;

final class BillingController
{
    private const GATEWAYS = ['paypal', 'razorpay'];

    public function __construct(
        private BillingConfig $cfg,
        private GatewayFactory $gateways,
        private PaymentRepository $payments,
        private EntitlementRepository $entitlements,
        private MeteringService $meter,
        private EntitlementResolver $resolver,
        private Auth $auth,
        private View $view,
        private Session $session,
    ) {}

    public function index(Request $req): Response
    {
        $uid = $this->auth->userId();
        return Response::html($this->view->render('billing/index', [
            'title' => 'Billing',
            'products' => $this->gateways->enabledProducts(),
            'gatewayNames' => self::GATEWAYS,
            'currency' => $this->cfg->currency(),
            'usage' => [
                'jobs' => $this->meter->jobsUsed($uid),
                'jobLimit' => $this->cfg->freeJobLimit(),
                'emails' => $this->meter->emailsUsed($uid),
                'emailLimit' => $this->cfg->freeEmailLimit(),
            ],
            'entitlement' => $this->entitlements->for($uid),
            'session' => $this->session,
            'error' => $this->session->getFlash('error'),
        ]));
    }

    public function checkout(Request $req): Response
    {
        $uid = $this->auth->userId();
        $product = (string) $req->input('product');
        $gatewayName = (string) $req->input('gateway');

        $products = $this->gateways->enabledProducts();
        if (!isset($products[$product])) {
            $this->session->flash('error', 'That product is not available.');
            return Response::redirect('/billing');
        }

        try {
            $gw = $this->gateways->get($gatewayName);
        } catch (InvalidArgumentException) {
            $this->session->flash('error', 'That payment method is not available.');
            return Response::redirect('/billing');
        }

        $amount = $products[$product];
        $currency = $this->cfg->currency();
        $order = $gw->createOrder($product, $amount, $currency, ['user_id' => $uid, 'product' => $product]);

        $this->payments->record([
            'user_id' => $uid,
            'gateway' => $gw->name(),
            'product' => $product,
            'gateway_ref' => $order['ref'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'created',
        ]);

        if (!empty($order['redirect_url'])) {
            return Response::redirect($order['redirect_url']);
        }

        return Response::html($this->view->render('billing/pay', [
            'title' => 'Complete payment',
            'gateway' => $gw->name(),
            'product' => $product,
            'order' => $order,
            'session' => $this->session,
        ]));
    }

    public function success(Request $req): Response
    {
        return Response::html($this->view->render('billing/success', [
            'title' => 'Payment received', 'session' => $this->session,
        ]));
    }

    public function cancel(Request $req): Response
    {
        return Response::html($this->view->render('billing/cancel', [
            'title' => 'Payment canceled', 'session' => $this->session,
        ]));
    }
}
