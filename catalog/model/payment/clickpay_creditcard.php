<?php

namespace Opencart\Catalog\Model\Extension\Clickpay\Payment;

require_once DIR_EXTENSION . 'clickpay/system/library/clickpay_api.php';

use Opencart\System\Library\ClickpayCatalogModel;

class ClickpayCreditcard extends ClickpayCatalogModel
{
	public $_code = 'creditcard';
}
