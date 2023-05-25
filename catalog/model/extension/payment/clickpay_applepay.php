<?php

namespace Opencart\Catalog\Model\Extension\Clickpay\Payment;

require_once DIR_EXTENSION . 'paytabs/system/library/clickpay_api.php';

use Opencart\System\Library\ClickpayCatalogModel;

class ClickpayApplepay extends ClickpayCatalogModel
{
	public $_code = 'applepay';
}
