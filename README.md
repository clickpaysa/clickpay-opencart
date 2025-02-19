# clickpay-opencart


The official **OpenCart** Plugin for ClickPay.

Supports OpenCart 4.x

---

## Installation

### Install using OpenCart Admin panel

#### OpenCart 4.x

1. Download the latest release of the plugin
2. Go to `"OpenCart admin panel" >> Extensions >> Installer`
3. Click `Upload`
4. Select the downloaded zip file (`clickpay_payment.ocmod.zip`)
5. Wait until the upload *Progress* success

*Note 1*: The new uploaded plugin will overwrite any previous version.

*Note 2*: By removing the Plugin from the `Extension Installer` admin page, You are removing the configurations of the plugin.

### Install using FTP method

#### OpenCart

1. Download the latest version (`clickpay.ocmod.zip`)
2. Upload the folder to `/opencart/system/storage/marketplace/`
3. Go to `"OpenCart admin panel" >> Extensions >> Installer`
4. On the plugin row `ClickPay - OpenCart`: Click **Install**

## Activating the Plugin

1. Go to `"OpenCart admin panel" >> Extensions >> Extensions`
2. Select `Payments` option from `Choose the extension type` section
3. Look for the preferred payment method from the available list of ClickPay payment methods *(`ClickPay - CreditCard` for example)*
4. Click the *Green plus* button next to the plugin and wait until the installation completes

---

## Configure the Plugin

1. Go to `"OpenCart admin panel" >> Extensions >> Extensions`
2. Select `Payments` option from `Choose the extension type` section
3. Look for the preferred payment method from the available list of ClickPay payment methods *(`ClickPay - CreditCard` for example)*
4. The edit button *(The blue button)* should be enabled for activated plugins, Click it
5. Select `Enable` for `Status` field
6. Enter the primary credentials:
   - **Profile ID**: Enter the Profile ID of your ClickPay account
   - **Server Key**: `Merchant’s Dashboard >> Developers >> Key management >> Server Key`
7. Configure other options as your need
8. Click the `Save` button *(The blue button on top-right of the page)* button

---

