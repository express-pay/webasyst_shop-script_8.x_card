<?php
return array(
    'CARD_EXPRESSPAY_TOKEN' => array(
        'value'         => '',
        'title'         => 'Токен',
        'description'   => 'API-ключ производителя услуг',
        'control_type'  => 'input'
    ),
    'CARD_EXPRESSPAY_SERVICEID' => array(
        'value'         => '',
        'title'         => 'Номер услуги',
        'description'   => 'Номер услуги в системе express-pay.by',
        'control_type'  => 'input'
    ),
    'CARD_EXPRESSPAY_SECRET_WORD' => array(
        'value'         => '',
        'title'         => 'Секретное слово',
        'description'   => 'Секретное слово используется для формирования цифровой подписи',
        'control_type'  => 'input'
    ),
    'CARD_EXPRESSPAY_INFO' => array(
        'value'         => 'Заказ номер ##order_id## в магазине ##shop_name##',
        'title'         => 'Описание заказа',
        'description'   => 'Описание заказу будет отображаться при оплате в системе',
        'control_type'  => 'help'
    ),
    'CARD_EXPRESSPAY_TESTMODE' => array(
        'value'         => false,
        'title'         => 'Тестовый режим',
        'description'   => '',
        'control_type' => 'checkbox'
    ),
);