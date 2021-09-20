<?php

/**
 *
 * @author ExpressPay
 * @name ExpressPay
 * @description Плагин оплаты через Банковские карты.
 *
 *
 */

class cardexpresspaypaymentPayment extends waPayment implements waIPayment
{

    public function allowedCurrency()
    {
        $currency = array('BYN');

        return $currency;
    }

    public function payment($payment_form_data, $order_data, $auto_submit = false)
    {
        // заполняем обязательный элемент данных с описанием заказа
        if (empty($order_data['description'])) {
            $order_data['description'] = 'Заказ ' . $order_data['order_id'];
        }
        $tmpCurrency = wa('shop')->getConfig()->getCurrency(true) == 'BYN' ? 933 : 000;

        $order = waOrder::factory($order_data);
        $url = $this->getRelayUrl();

        $info = $this->CARD_EXPRESSPAY_INFO;
        if (empty($info)) {
            $info = "Заказ номер ##order_id## в магазине ##shop_name##";
        }

        $info = str_replace('##order_id##', $order_data['order_id'], $info);
        $info = str_replace('##shop_name##', $_SERVER['SERVER_NAME'], $info);

        $hidden_fields = array(
            'ServiceId'     => $this->CARD_EXPRESSPAY_SERVICEID,
            'AccountNo'     => str_replace('#', '', $order_data['id_str']),
            'Amount'        => number_format($order->total, 2, '.', ''),
            'Currency'      => $tmpCurrency,
            'ReturnType'    => "Redirect",
            "Info"          => $info,
            'ReturnUrl'     => 'http://' . $_SERVER['HTTP_HOST'] . "/payments.php/cardexpresspaypayment/?app_id=" . $this->app_id . '_' . $this->merchant_id . '_' . $order_data['order_id'],
            'FailUrl'       => $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL),

        );

        $hidden_fields['Signature'] = $this->compute_signature_add_invoice($hidden_fields, $this->CARD_EXPRESSPAY_SECRET_WORD, $this->CARD_EXPRESSPAY_TOKEN);
        $view = wa()->getView();

        $isTest = $this->CARD_EXPRESSPAY_TESTMODE;
        $CARD_EXPRESSPAY_SANDBOX_API_URL =  'https://sandbox-api.express-pay.by';
        $CARD_EXPRESSPAY_API_URL =  'https://api.express-pay.by';

        if (isset($isTest) && $isTest) {
            $url = $CARD_EXPRESSPAY_SANDBOX_API_URL;
        } else {
            $url = $CARD_EXPRESSPAY_API_URL;
        }
        //$url= "http://localhost:10456";//тестовый сервер
        $url =   $url . "/v1/web_cardinvoices";

        $view->assign('url', $url);
        $view->assign('hidden_fields', $hidden_fields);
        $view->assign('auto_submit', $auto_submit);
        return $view->fetch($this->path . '/templates/payment.html');
    }

    function compute_signature_add_invoice($request_params, $secret_word, $token)
    {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array(
            "serviceid",
            "accountno",
            "expiration",
            "amount",
            "currency",
            "info",
            "returnurl",
            "failurl",
            "language",
            "sessiontimeoutsecs",
            "expirationdate",
            "returntype",
        );

        $result = $token;

        foreach ($api_method as $item)
            $result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));
        return $hash;
    }

    function computeSignatureCard($request_params, $secret_word, $token)
    {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array(
            "expresspayaccountnumber",
        );

        $result = $token;
        foreach ($api_method as $item)
            $result .= (isset($normalized_params[$item])) ? strtolower($normalized_params[$item]) : '';

        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

        return $hash;
    }

    protected function callbackInit($request)
    {
        $pattern = '/^([a-z]+)_(.+)_(.+)$/';
        $pattern2 = '/^([a-z]+)_(.+)$/';
        if (isset($request['action']) && $request['action'] == 'notify' && !empty($request['app_id']) && preg_match($pattern2, $request['app_id'], $match)) {
            $data = $_POST['Data'];
            $data = json_decode($data, true);
            $this->app_id = $match[1];
            $this->merchant_id = $match[2];

            $strtmp = str_replace('{$order.id}', '', wa()->getSetting('order_format'));
            $strtmp = str_replace('#', '', $strtmp);
            $this->order_id = str_replace($strtmp, '', $data['AccountNo']);
        } elseif (!empty($request['app_id']) && preg_match($pattern, $request['app_id'], $match)) {
            $this->app_id = $match[1];
            $this->merchant_id = $match[2];
            $this->order_id = $match[3];
        }

        return parent::callbackInit($request);
    }

    protected function callbackHandler($request)
    {
        if (isset($request['action']) && $request['action'] == 'notify') {
            $data = $_POST['Data'];
            $signature = $_POST['Signature'];
            $data = json_decode($data, true);
            $transaction_data = $this->formalizeDataTwo($request, $data);
        } elseif (isset($request['ExpressPayAccountNumber']) && isset($request['Signature'])) {
            $dataTest = array(
                'ExpressPayAccountNumber'             => $_REQUEST['ExpressPayAccountNumber']
            );
            $sign = $this->computeSignatureCard($dataTest, $this->CARD_EXPRESSPAY_SECRET_WORD, $this->CARD_EXPRESSPAY_TOKEN); //Генерация подписи из полученных данных
            $signature = $_REQUEST['Signature'];

            if ($signature == $sign) //проверка на совпадение подписей
            {
                $transaction_data = $this->formalizeData($request);
                $transaction_data['type'] = self::OPERATION_AUTH_CAPTURE;
            } else {
                return array(
                    'redirect' => $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data),
                );
            }
        } else {
            return array(
                'redirect' => $this->getAdapter()->getBackUrl(waAppPayment::URL_FAIL, $transaction_data),
            );
        }



        switch ($transaction_data['type']) {
            case self::OPERATION_CHECK: //в обработке-> обработан           
                $app_payment_method = self::CALLBACK_CONFIRMATION;
                $transaction_data['state'] = self::STATE_AUTH;
                break;
            case self::OPERATION_AUTH_CAPTURE: //оплата
                $app_payment_method = self::CALLBACK_PAYMENT;
                $transaction_data['state'] = self::STATE_CAPTURED;
                break;
            case self::OPERATION_CANCEL: //отмена
                $app_payment_method = self::CALLBACK_REFUND;
                $transaction_data['state'] = self::STATE_CANCELED;
                break;
                /*
            case self::OPERATION_OTMENEN://отмена
                $app_payment_method = self::CALLBACK_OTMENEN;
                $transaction_data['state'] = self::STATE_OTMENEN;
                break;*/
            default:
                break;
        }
        // сохраняем данные транзакции в базу данных
        $transaction_data = $this->saveTransaction($transaction_data, $request);

        // вызываем соответствующий обработчик приложения для каждого из поддерживаемых типов транзакций
        $result = $this->execAppCallback($app_payment_method, $transaction_data);

        if (isset($request['action']) && $request['action'] == 'notify') {
            header("HTTP/1.0 200 OK");
            echo 'SUCCESS';
        } else {
            return array(
                'redirect' => $this->getAdapter()->getBackUrl(waAppPayment::URL_SUCCESS),
            );
        }
    }
    function formalizeData($request)
    {
        // формируем полный список полей, относящихся к транзакциям
        $fields = array(
            'wa_app',
            'wa_merchant_contact_id'

        );
        foreach ($fields as $f) {
            if (!isset($request[$f])) {
                $request[$f] = null;
            }
        }
        // выполняем базовую обработку данных
        $transaction_data = parent::formalizeData($request);
        // добавляем дополнительные данные:

        $transaction_data['native_id'] = $request['ExpressPayInvoiceNo'];

        // номер заказа
        $strtmp = str_replace('{$order.id}', '', wa()->getSetting('order_format'));
        $strtmp = str_replace('#', '', $strtmp);
        $transaction_data['order_id'] = str_replace($strtmp, '', $request['ExpressPayAccountNumber']);

        // сумма заказа
        $transaction_data['amount'] = $request['amount'];
        // идентификатор валюты заказа
        $transaction_data['currency_id'] = 'BYN';

        return $transaction_data;
    }

    function formalizeDataTwo($request, $data)
    {
        // формируем полный список полей, относящихся к транзакциям, которые обрабатываются платежной системой WebMoney
        $fields = array(
            'wa_app',
            'wa_merchant_contact_id'

        );
        foreach ($fields as $f) {
            if (!isset($request[$f])) {
                $request[$f] = null;
            }
        }
        foreach ($fields as $f) {
            if (!isset($request[$f])) {
                $request[$f] = null;
            }
        }
        // выполняем базовую обработку данных
        $transaction_data = parent::formalizeData($request);

        if ($data['CmdType'] == 1) {
            $transaction_data['type'] = self::OPERATION_AUTH_CAPTURE;
        } elseif ($data['CmdType'] == 2) {
            $transaction_data['type'] = self::OPERATION_CANCEL; //OPERATION_OTMENEN;
        } elseif ($data['CmdType'] == 3 && $data['Status'] == 3) {
            $transaction_data['type'] = self::OPERATION_AUTH_CAPTURE;
        }

        $transaction_data['native_id'] =  $data['AccountNo'];

        // номер заказа
        $strtmp = str_replace('{$order.id}', '', wa()->getSetting('order_format'));
        $strtmp = str_replace('#', '', $strtmp);
        $transaction_data['order_id'] =  str_replace($strtmp, '', $data['AccountNo']);

        // сумма заказа
        $transaction_data['amount'] = $data['Amount'];

        // идентификатор валюты заказа
        $transaction_data['currency_id'] = 'BYN';

        return $transaction_data;
    }
}
