<?php

namespace frontend\controllers;

use common\helpers\FormatterHelper;
use common\models\carriers\Carriers;
use common\models\carriers\CarriersPickme;
use common\models\carriers\CarriersRates;
use common\models\carriers\CarriersShippingCountryZones;
use common\models\carriers\PaymentMethods;
use common\models\countries\Countries;
use common\models\customers\Customers;
use common\models\customers\CustomersAddresses;
use common\models\custompage\CustomPage;
use common\models\discounts\Discounts;
use common\models\MainConfiguration;
use common\models\offers\OffersConfigurations;
use common\models\orders\OrderPayments;
use common\models\orders\Orders;
use common\models\orders\OrdersDiscounts;
use common\models\orders\OrdersLines;
use common\models\orders\OrdersObservations;
use common\models\orders\OrdersStatus;
use common\models\Product;
use common\models\products\Products;
use common\models\shipping\ShippingCountryZones;
use common\models\stock\Stock;
use frontend\helpers\LinkHelper;
use frontend\helpers\PaymentHelper;
use frontend\helpers\StatisticsHelper;
use Yii;
use yii\db\Exception;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\web\Controller;
use yii2mod\cart\Cart;

class CartController extends Controller {

    //Layout to be used by all views
   // public $layout = 'cart';

    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'remove' => ['post'],
                    'refresh' => ['post'],
                    'addoffertocart' => ['post']
                ],
            ],
        ];
    }

    public function actionCheckout(){
        $counter=0;
        $cart = \Yii::$app->cart;
        // get all items from the cart
        $items = $cart->getItems();

        // get only products
        $items = $cart->getItems(Cart::ITEM_PRODUCT);

        $order= new \common\models\Orders();
        $order->client_id=Yii::$app->getUser()->id;
        $order->save();
        // loop through cart items
        foreach ($items as $item) {
            // access any attribute/method from the model
            var_dump($item->getAttributes());
            // remove an item from the cart by its ID
            $product= Product::findOne(['id'=>$item->id]);
            $product->quantity-=$item->quantity;
            $cart->remove($item->uniqueId);
            $orederline= new \common\models\OrdersLines();
            $orederline->id_order=$order->id;
            $orederline->id_product=$item->id;
            $orederline->quantity=$item->quantity;
            $orederline->product_name= $item->name;
            $orederline->product_price= $item->price * $item->quantity;
            $counter+= $orederline->product_price;
            $orederline->save();
            $product->save();

        }


        $order->total_price=$counter;
        $order->save();

        return $this->render('order', ['order'=>$order]);


    }
    public function actionRegister($id_order = null) {

        $post = Yii::$app->request->post();
        $cart = Yii::$app->cart;
        if (!$cart->refresh(true, true)) {
            return LinkHelper::redirect(['/cart']);
        }

        if (empty($id_order)) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $paymentMethodId = $cart->getPaymentMethod();
                $order = new Orders();
                $order->id_shop = 1;
                $order->ref_order = $order::newReference();
                $order->id_customer = !empty(Yii::$app->user->id) ? Yii::$app->user->id : 1;
                $addr_ship = $cart->getAddress('shipping');
                $addr_bill = $cart->getAddress('billing');
                $extra_data = [];
                $carrier = $cart->getCarrier();
                $extra_data['weight'] = FormatterHelper::formatNumber($cart->getWeight());
                /*
                 * shipping_address = morada normal
                 * shipping_address_pickme = morada normal pick me
                 *
                 * pickme_alternate
                 *
                 */
                $pickMe = $onlyPickMe = false;

                if (isset(Yii::$app->params['pickMe']['pickMeOnly']) && Yii::$app->params['pickMe']['pickMeOnly'] == $carrier) {
                    $pickMe = $onlyPickMe = true;
                    // Pick Me - Only !
                    $pickme_addr = $cart->getAddress('pickme');
                    $extra_data["shipping_address"] = (array) $pickme_addr->attributes;
                    $extra_data['shipping_address']['id_country'] = 178;
                    $extra_data['shipping_address']['country'] = "Portugal";
                    $extra_data['shipping_address']['pickme'] = 1;
                    if ($pickme_addr->postal_code{0} >= 9) {
                        $extra_data['shipping_address']['country_zone'] = "Ilhas";
                    } else {
                        $extra_data['shipping_address']['country_zone'] = "Continental";
                    }
                    $extra_data['shipping_address']['delivery_date'] = $cart->getPickMeDate();
                    $order->id_shipping_address = null;
                } elseif (in_array($carrier, Yii::$app->params['pickMe']['id_carrier'])) {
                    $pickMe = true;
                    // Pick Me & Address !
                    $extra_data["shipping_address"] = (array) $addr_ship->attributes;
                    $shippingCountry = $addr_ship->countryZone->country;
                    $country = Countries::find()->byId($shippingCountry->id_country)->one();
                    $extra_data['shipping_address']['id_country'] = $country->id_country;
                    $extra_data['shipping_address']['country'] = $country->name_common;
                    $extra_data['shipping_address']['country_zone'] = $addr_ship->countryZone->title;
                    $order->id_shipping_address = $addr_ship->id_customer_address;
                    // Pick Me - 3rd Address
                    $pickme_addr = $cart->getAddress('pickme');
                    $extra_data["shipping_address"]['pickme_alternate'] = 1;
                    $extra_data["shipping_address_pickme"] = (array) $pickme_addr->attributes;
                    $extra_data['shipping_address_pickme']['id_country'] = 178;
                    $extra_data['shipping_address_pickme']['country'] = "Portugal";
                    if ($pickme_addr->postal_code{0} >= 9) {
                        $extra_data['shipping_address_pickme']['country_zone'] = "Ilhas";
                    } else {
                        $extra_data['shipping_address_pickme']['country_zone'] = "Continental";
                    }
                    $extra_data['shipping_address_pickme']['delivery_date'] = $cart->getPickMeDate();
                } else {
                    // Normal Address
                    $extra_data["shipping_address"] = (array) $addr_ship->attributes;
                    $shippingCountry = $addr_ship->countryZone->country;
                    $country = Countries::find()->byId($shippingCountry->id_country)->one();
                    $extra_data['shipping_address']['id_country'] = $country->id_country;
                    $extra_data['shipping_address']['country'] = $country->name_common;
                    $extra_data['shipping_address']['country_zone'] = $addr_ship->countryZone->title;
                    $order->id_shipping_address = $addr_ship->id_customer_address;
                }

                $extra_data["billing_address"] = (array) $addr_bill->attributes;

                if (!isset($extra_data ["billing_address"]["vat"]) || empty($extra_data["billing_address"]["vat"])) {
                    $extra_data["billing_address"]["vat"] = "ConsumidorFinal";
                }

                $billingCountry = $addr_bill->countryZone->country;
                $country = Countries::find()->byId($billingCountry->id_country)->one();
                $extra_data['billing_address']['id_country'] = $country->id_country;
                $extra_data['billing_address']['country'] = $country->name_common;
                $extra_data['billing_address']['country_zone'] = $addr_bill->countryZone->title;
                $carrierInfo = Carriers::findOne($carrier);
                $extra_data['carrier'] = (array) $carrierInfo->attributes;

                // id sent to google analytics for payment refund:
                //$idGoogleEcommerce = $post['googleEcommerceId'];
                //$extra_data['googleEcommerceId'] = $idGoogleEcommerce;
                //
                $extraDataOffers = array();
                foreach ($cart->positions as $line) {
                    if ($line->isOffer()) {
                        $extra_data['is_offer'] = 'true';
                        $offer = $line->getOffer();
                        $extraDataOffers[$offer->id_offer] = $offer->description;
                    }
                }

                $extra_data['offers'] = count($extraDataOffers) > 0 ? $extraDataOffers : null;

                $order->extra_data = json_encode($extra_data);
                $order->id_billing_address = $addr_bill->id_customer_address;
                $order->price_articles = $cart->getCost(false);
                $order->price_cartdiscount = round($order->price_articles - $cart->getCost(true), 2);
                $stored_address = $cart->getAddress('shipping');


                if ($onlyPickMe == true) {
                    // >= 9 ? Ilhas : Portugal Continental
                    $id_country_zone = $cart->getAddress('pickme')->postal_code{0} >= 9 ? 6 : 5;
                    $shippingCountryZone = CarriersShippingCountryZones ::getCountryZoneByCarrier($carrier, $id_country_zone);
                    $carrierRates = CarriersRates::getRates($shippingCountryZone->id_carrier_country_zone, $cart->getWeight(), $cart->getCost(true));
                    $carrierRate = CarriersRates::getCartFinalCost(Yii::$app->cart->getCost(true), $carrierInfo, $carrierRates, $shippingCountryZone->id_shipping_country_zone);
                } else {
                    $carrierRate = $stored_address->getCarrierRate($cart->getCarrier(), $cart->getWeight(), $cart->getCost(true));
                }

                $shippingCosts = $carrierRate['freeShipping'] == true ? 0 : $carrierRate['shippingCost'];
                $order->price_shipping = $shippingCosts;
                $order->price_total = $carrierRate['finalCartCost'];
                if ($order->validate()) {
                    $order->save();
                    // Register Observation
                    if (!empty(Yii::$app->request->post('observation'))) {
                        $observation = OrdersObservations::registerNewObservation([
                                    'observation' => Yii::$app->request->post('observation'),
                                    'id_order' => $order->primaryKey,
                                    'observation_date' => date("Y-m-d H:i:s"),
                                    'related_id' => Yii::$app->user->id,
                                    'related_class' => get_class(Yii::$app->user->identity),
                                    'related_attribute' => 'name',
                                    'extra_data' => Json::encode(['name' => Yii::$app->user->identity->name]),
                                    'is_private' => 0
                        ]);
                    }
                    $hasPromo = $cart->registerDiscounts($order->primaryKey);
                    $cart_discounts = $cart->discounts;
                    $extra_data = [];
                    /* STA - Order Email HTML */
                    $mailTexts = [];
                    $products_table = "";
                    $mailTexts['product'] = Yii::t('frontend/order', 'Produto');
                    $mailTexts['quantity'] = Yii::t('frontend/order', 'Quantidade');
                    $mailTexts['total'] = Yii::t('frontend/order', 'Total');
                    $mailTexts['shippingCosts'] = Yii::t('frontend/order', 'Custo de Envio');
                    $mailTexts['totalwVAT'] = Yii::t('frontend/order', 'Total (c/ IVA)');
                    $products_table .= <<<EOT
                    <table width="100%" border="0" align="center" cellpadding="0" cellspacing="0">
                        <thead>
                            <tr>
                                <th width="75%" align="center">{$mailTexts['product']}</th>
                                <th width="10%" align="center">{$mailTexts['quantity']}</th>
                                <th width="15%" align="center">{$mailTexts['total']}</th>
                            </tr>
                        </thead>
                        <tbody>
EOT;
                    /* END - Order Email HTML */

                    foreach ($cart->positions as $line) {
                        StatisticsHelper::addStatistic('order', 'Products', $line->getId(), $line->getQuantity());
                        $line->handleStock();
                        $qty = $line->getQuantity();
                        $id_product = $line->getId();
                        $id_combination = $line->getIdCombination();
                        $final_price = $line->getCost(true);
                        $product_price = $line->getCost(false);
                        if (is_array($cart_discounts) && array_key_exists($line->getIndex(), $cart_discounts)) {
                            $id_discount = $cart_discounts[$line->getIndex()]['id_discount'];
                            $id_discount_condition = $cart_discounts[$line->getIndex()]['id_discount_condition'];
                            //actual discount percentage
                            $discount_info = Discounts::findOne($id_discount);
                        } else {
                            $id_discount = null;
                            $id_discount_condition = null;
                            //actual discount percentage
                            $discount_info = null;
                        }

                        $costInfo = $line->getCostInfo();
                        $product_discount_value_per_unit = FormatterHelper::formatNumber($costInfo['discountAmount'] / $qty, 2);
                        $product_price_unit = FormatterHelper::formatNumber($costInfo['discountCost'] / $qty, 2);
                        $product_price_unit_with_discount = $line->getCost(true, null, null, false);
                        /*
                         * Calculate Discount Value
                         * If lower then 0, fix it 0
                         * If higher then Product Price, fix it at Product Price
                         */
                        $product_discount_value = $product_price - $final_price;
                        $product_discount_value = ($product_discount_value < 0) ? 0 : $product_discount_value;
                        $product_discount_value = ($product_discount_value > $product_price) ? $product_price : $product_discount_value;
                        $productName = $line->getName();
                        $extra_data["attributes"] = $line->getExtraAttributes();
                        $extra_data["price_individual_with_iva"] = FormatterHelper ::formatNumber($product_price / $qty, 2);
                        $extra_data["price_individual"] = $extra_data["price_individual_with_iva"];
                        $extra_data["price_individual_without_discount"] = $product_price_unit;
                        $extra_data["price_individual_with_discount"] = $product_price_unit_with_discount;
                        $extra_data["price_discount_unit"] = $product_discount_value_per_unit;
                        $extra_data["product_reference"] = $line->reference;

                        if (is_object($discount_info)) {
                            $extra_data['discount_id'] = $discount_info->id_discount;
                            $extra_data['discount_name'] = $discount_info->name;
                            $extra_data['discount_type_of_reduction'] = $discount_info->type_of_reduction;
                            $extra_data['discount_reduction_value'] = $discount_info->reduction_value;
                        }
                        if ($line->isOffer()) {
                            $extra_data['is_offer'] = 'true';
                            $offer = $line->getOffer();
                            if ($offer) {
                                $extra_data['offer_description'] = $offer->description;
                            }
                        } else {
                            $extra_data['is_offer'] = 'false';
                        }

                        $order_line = new OrdersLines;
                        $order_line->id_order = $order->id_order;
                        $order_line->id_product = $id_product;
                        $order_line->id_combination = $id_combination;
                        $order_line->id_discount = $id_discount;
                        $order_line->id_discount_condition = $id_discount_condition;
                        $order_line->product_name = $productName;
                        $order_line->qty = $qty;
                        $order_line->product_price = $product_price;
                        $order_line->product_discount = $product_discount_value;
                        $order_line->final_price = $final_price;
                        $order_line->extra_data = json_encode($extra_data);
                        if ($order_line->validate()) {
                            $order_line->save();
                            if ($id_discount) {
                                $order_line_discount = new OrdersDiscounts;
                                $order_line_discount->id_order = $order->id_order;
                                $order_line_discount->id_discount = $id_discount;
                                $order_line_discount->id_discount_condition = $id_discount_condition;
                                $order_line_discount->applied_to = $order_line->id_order_line;
                                if ($order_line_discount->validate()) {
                                    $order_line_discount->save();
                                }
                            }
                        }
                        $formatted_price = FormatterHelper::displayNumber($final_price);
                        $products_table .= <<<EOT
                            <tr>
                                <td align="center">
                                    {$productName}
                                </td>
                                <td align="center">
                                    {$qty}
                                </td>
                                <td align="center">
                                    {$formatted_price}
                                </td>
                            </tr>
EOT;
                    }
                    $order_status = new OrdersStatus;
                    $order_status->id_order = $order->id_order;
                    $order_status->order_status = 'waitingpayment';

                    if ($paymentMethodId) {
                        $paymentMethodsTypes = PaymentHelper::listPaymentTypes();
                        $paymentMethod = PaymentMethods::findOne($paymentMethodId);
                        $paymentMethodName = $paymentMethodsTypes[$paymentMethod->title];
                        $order_status->reason = $paymentMethodName;
                    }
                    if ($order_status->validate()) {
                        $order_status->save();
                    }

                    /* Send Register Order Email */
                    $formatted_shippingcosts = FormatterHelper::displayNumber($shippingCosts);
                    $formatted_total = FormatterHelper::displayNumber($order->price_total);
                    $products_table .= <<<EOT
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3">&nbsp;</td></tr>
                            <tr><td colspan="3">&nbsp;</td></tr>
                            <tr>
                                <td colspan="2" align="right">{$mailTexts['shippingCosts']}:</td>
                                <td align="right">{$formatted_shippingcosts}</td>
                            </tr>
                            <tr>
                                <td colspan="2" align="right">{$mailTexts['totalwVAT']}:</td>
                                <td align="right">{$formatted_total}</td>
                            </tr>
                        </tfoot>
                    </table>
EOT;
                    // 3 == Criação da encomenda
                    Yii::$app->mailer->emailTemplate(3, Yii::$app->user->identity->email, [
                        'client' => Yii::$app->user->identity->name,
                        'ident' => $order->ref_order,
                        'total' => $formatted_total,
                        'peso' => Yii::$app->cart->getWeight(),
                        'data' => date("Y-m-d H:i"),
                        'products_table' => $products_table
                    ]);

                    //Enviar Cópia para o admin.
                    $notificationEmail = MainConfiguration::find()->where(['config_key' => 'notificationEmail'])->limit(1)->one();
                    if (!empty($notificationEmail) && !empty($notificationEmail->config_value)) {
                        Yii::$app->mailer->emailTemplate(3, $notificationEmail->config_value, [
                            'client' => Yii::$app->user->identity->name,
                            'ident' => $order->ref_order,
                            'total' => $formatted_total,
                            'peso' => Yii::$app->cart->getWeight(),
                            'data' => date("Y-m-d H:i"),
                            'products_table' => $products_table
                        ]);
                    }

                    $id_order = $order->id_order;
                }
                $transaction->commit();
            } catch (Exception $ex) {
                $transaction->rollBack();
                throw $ex;
            }
        } else {
            if (!empty($post['payment'])) {
                $paymentMethodId = $post['payment'];
            }
        }

        if (!empty($paymentMethodId)) {
            $order_payment = new OrderPayments;
            $order_payment->id_order = $id_order;
            $order_payment->id_payment_method = $paymentMethodId;
            if ($order_payment->validate()) {
                $order_payment->save();
            }
        }
        if (!empty($paymentMethodId) && !empty($id_order)) {
            $cart->removeAll();
            LinkHelper::redirect([
                '/payments/implementation/pay',
                'id_order' => $id_order
            ]);
        } elseif (empty($id_order)) {
            LinkHelper::redirect(['/cart/shipping']);
        } else {
            LinkHelper::redirect([
                '/customer/orderdetail',
                'id' => $id_order
            ]);
        }
    }

    /* STEP 1 - LIST */

    public function actionIndex($placeholder = null) {
        $cart = Yii::$app->cart;
        //$cart->refresh();


        /* Offers */
       // $offers = new OffersConfigurations();
        //$validOffersFromProducts = $offers->getProductOffers($cart->getPositions(), $cart->getCost(), $cart->getWeight());
        //$validGlobalOffers = $offers->getOffers($cart->getWeight(), $cart->getCost(), $cart->getCount());
        //$validFirstBuyOffers = $offers->getFirstBuyOffers($cart->getWeight(), $cart->getCost(), $cart->getCount());
        //$offersInCart = $cart->getOffers();
        //$validOffers = $validOffersFromProducts + $validGlobalOffers + $validFirstBuyOffers;
        // Remove Offers from Cart
        //$cart->removeOffers();
        //$unusedAndPartiallyUnusedOffers = $this->addProductOffersToCart($validOffers, $cart);
        // Refresh Positions
       // $positions = $cart->getPositions();

        //seo
        $param = [
            'title' => Yii::t('app', 'Carrinho'),
            'description' => '',
            'keywords' => '',
        ];
      //  $this->setSeoParam('cart', $param);

        return $this->render('list', [
                   // 'positions' => $positions,
                    'cart' => $cart
                    //'unusedAndPartiallyUnusedOffers' => $unusedAndPartiallyUnusedOffers
        ]);
    }

    public function actionAddoffertocart($id) {

        $cart = Yii::$app->cart;
        $positions = $cart->getPositions();
        $offers = new OffersConfigurations();
        $validOffersFromProducts = $offers->getProductOffers($positions, $cart->getCost(), $cart->getWeight());
        $validGlobalOffers = $offers->getOffers($cart->getWeight(), $cart->getCost(), $cart->getCount());
        $validFirstBuyOffers = $offers->getFirstBuyOffers($cart->getWeight(), $cart->getCost(), $cart->getCount());
        $offersInCart = $cart->getOffers();
        $validOffers = $validOffersFromProducts + $validGlobalOffers + $validFirstBuyOffers;

        foreach ($validOffers as $k => $validOffer) {
            if ($id == $validOffer->id_offer) {
                $cart->setOffer(['id_offer' => $id, 'status' => 'cart']);
            }
        }

        return $this->redirect(['/cart']);
    }

    protected function addProductOffersToCart($offers, $cart) {
        if (count($offers) > 0) {
            $cartOffers = $cart->getOffers();
            $offersToIgnore = [];
            $unusedOffers = [];
            $partiallyUnusedOffers = [];
            if (count($cartOffers) > 0) {
                foreach ($cartOffers as $cartOffer) {
                    if ($cartOffer['status'] == 'refused') {
                        $offersToIgnore[] = $cartOffer['id_offer'];
                        $unusedOffers [] = $cartOffer['id_offer'];
                    }
                    if ($cartOffer['status'] == 'partial') {
                        $offersToIgnore[] = $cartOffer['id_offer'];
                        $partiallyUnusedOffers [] = $cartOffer['id_offer'];
                    }
                    if ($cartOffer['status'] == 'cart') {
                        $offersToIgnore[] = $cartOffer['id_offer'];
                    }
                }
            }

            foreach ($offers as $offer) {
                if (!in_array($offer->id_offer, $offersToIgnore)) {
                    // Get Products
                    $productsInOffer = $offer->getOfferProducts();
                    // Iterate Products
                    if ($productsInOffer && count($productsInOffer) > 0) {
                        foreach ($productsInOffer as $productInOffer) {
                            $product = $productInOffer->getProduct();

                            if ($product) {
                                // Add Product to Cart
                                $product->markAsOffer($offer);
                                $cart->put($product, $productInOffer->quantity);
                            }
                        }
                    }
                }
            }
            return ['unusedOffers' => $unusedOffers,
                'partiallyUnusedOffers' => $partiallyUnusedOffers];
        } else {
            return [];
        }
    }

    public function actionRefresh() {
        $post = Yii::$app->request->post('Quantity');
        $cart = Yii::$app->cart;

        if (count($post) > 0) {
            $k = 0;
            $errorMessage = "<strong>" . Yii::t('frontend/cart', "Não existe quantidade suficiente para atualizar") . "</strong>";
            $successMessage = "<strong>" . Yii::t('frontend/cart', 'Quantidade atualizadas com sucesso') . "</strong>";
            $hasErrors = $thereWereSuccesses = false;
            foreach ($post as $positionIndex => $qty) {
                $position = $cart->getPositionById($positionIndex);
                if ($position->isOffer())
                    continue;
                // warehouse is being ignored
                $id_combination = $position->getIdCombination();

                $stock = Stock::returnProductStock($position->getId(), $id_combination, 0);

                if (($stock && $stock->available_stock >= $qty) || $position->handle_stock == 0) {
                    $hasErrors = false;
                    $cart->update($position, $qty);
                    $successMessage .= '<br> -› ' . $position->getName() . " (<strong> " . Yii::t('frontend/cart', '{x} unidades', ['x' => $qty]) . " </strong>)";
                    $specialName = $position->getName(true);
                    if (is_array($specialName)) {
                        if (!empty($specialName [2]->image))
                            $successMessage .= ' <img height="20" width="20" src="' . Yii::$app->request->BaseUrl . '/../content/uploads' . $specialName[2]->image . '">';
                    }
                } else {
                    $hasErrors = true;
                    $errorMessage .= '<br> -› ' . $position->getName();
                    $specialName = $position->getName(true);
                    if (is_array($specialName)) {
                        if (!empty($specialName [2]->image))
                            $errorMessage .= ' <img height="20" width="20" src="' . Yii::$app->request->BaseUrl . '/../content/uploads' . $specialName[2]->image . '">';
                    }
                }
            }
            if ($hasErrors) {
                Yii::$app->getSession()->setFlash('danger', [
                    'status' => 'danger',
                    'message' => $errorMessage
                ]);
            } else {
                Yii::$app->getSession()->setFlash('success', [
                    'status' => 'success',
                    'message' =>
                    $successMessage
                ]);
            }
        }
        return $this->redirect(['/cart']);
    }

    public function actionRemove($index) {
        $cart = Yii::$app->cart;
        $position = $cart->getPositionById($index);
        if ($position) {
            if ($position->isOffer()) {
                $offer = $position->getOffer();
                if ($offer->wholeOnly == 1 || $offer->optional == 0) {
                    // Remove all products from this offer (because you can't take partial here)
                    $cart->removeOfferAndItsProducts($offer->id_offer);
                    Yii::$app->getSession()->setFlash('success', [ 'status' => 'success',
                        'message' => Yii::t('frontend/cart', 'Oferta removida com sucesso')
                    ]);
                } else {
                    // im stupid still cant do this, so I remove all for now
                    $cart->removeOfferAndItsProducts($offer->id_offer);
                    /*
                      // Remove this product from offer, allows partial, and mark the offer, so that product doesn't get added again
                      $cart->removeById($index);
                      $cart->setOffer(['id_offer' => $offer->id_offer, 'status' => 'partial']);
                     *
                     */ Yii::$app->getSession()->setFlash('success', [ 'status' => 'success',
                        'message' => Yii::t('frontend/cart', 'Produto de Oferta removido com sucesso')
                    ]);
                }
            } else {
                $cart->removeById($index);
                Yii::$app->getSession()->setFlash('success', [ 'status' => 'success',
                    'message' => Yii::t('frontend/cart', 'Produto removido com sucesso')
                ]);
            }
            $get = Yii::$app->request->get();

            if (isset($get['referer'])) {
                return $this->redirect(Yii::$app->request->referrer);
            }
            return $this->redirect(['/cart']);
        } else {

            return $this->redirect(Yii::$app->request->referrer);
        }
    }

    /* STEP 2 - SHIPPING AND BILLING */

    public function actionShipping($placeholder = null) {
        $cart = Yii::$app->cart;
        $positions = $cart->getPositions();
        if (empty($positions)) {
            return $this->goHome();
        }

        //seo
        $param = [ 'title' => Yii::t('frontend/customer', 'Resumo Encomenda'),
            'description' => '',
            'keywords' => '',
        ];
        $this->setSeoParam('cart', $param);

        if (!Yii::$app->user->isGuest) {
            //customer info
            $customer = Customers::findOne(Yii::$app->user->id);

            //customer addresses
            $addresses = Yii::$app->user->identity->addresses;

            //default addresses
            $defaultShipping = CustomersAddresses::find()->where(['is_default_shipping' => 1, 'id_customer' => $customer->id_customer])->one();
            $defaultBilling = CustomersAddresses::find()->where(['is_default_billing' => 1, 'id_customer' => $customer->id_customer])->one();

            return $this->render('shipping', [
                        'positions' => $positions,
                        'cart' => $cart,
                        'addresses' => $addresses,
                        'defaultShipping' => $defaultShipping,
                        'defaultBilling' => $defaultBilling,
                        'dontRegisterJs' => true
            ]);
        } else {
            return $this->render('shipping');
        }
    }

    /* STEP 3 - CARRIERS */

    public function actionCarrier($placeholder = null) {
        $cart = Yii::$app->cart;
        $positions = $cart->getPositions();

        if (empty($positions)) {
            return $this->goHome();
        }

        //uncomment if needed
//        $pickUpMessageModel = CustomPage::find()->byId(122)->visible()->one();
//        $pickUpMessage = (!empty($pickUpMessageModel)) ? $pickUpMessageModel->description : Yii::t('frontend/customer', 'Data de entrega estimada para 3 dias');

        $post = Yii::$app->request->post('Carriers');

        if (!empty($post)) {
            if (isset($post['id_shipping_address']) && !empty($post['id_shipping_address'])) {
                $shippingAddress = CustomersAddresses::findOne($post['id_shipping_address']);


                /*if (!$shippingAddress) {
                    return LinkHelper::redirect(['/cart/carrier']);
                }*/

                $cart->storeAddress($shippingAddress);
            }

            if (isset($post['id_billing_address'])) {
                $billingAddress = CustomersAddresses::findOne($post['id_billing_address']);


               /* if (!$billingAddress) {
                    return LinkHelper::redirect(['/cart/shipping']);
                }*/

                $cart->storeAddress($billingAddress, 'billing');
            }
        } else {
            $shippingAddress = $cart->getAddress('shipping');
            $billingAddress = $cart->getAddress('billing');
        }

        /*if (empty($shippingAddress) || empty($billingAddress)) {
            return LinkHelper::redirect(['/cart/shipping']);
        }*/

        //uncomment if needed
//        $selectedCarrier = 0;
//        $pickme_code = '';
//        $selectedPickMe = '{}';
//        if ($cart->getCarrier()) {
//            $selectedCarrier = $cart->getCarrier();
//            if (in_array($selectedCarrier, Yii::$app->params['pickMe']['id_carrier'])) {
//                $pickme_code = $cart->getPickMe();
//                $selectedPickMe = $this->getSelectedPickMe($pickme_code);
//            }
//        }
        //get carriers
        $carriersInstance = new Carriers();
        $carriers = $carriersInstance->getCarriers(1);

        //seo
        $param = [ 'title' => Yii::t('frontend/customer', 'Escolher Transportadora'),
            'description' => '',
            'keywords' => '',
        ];
        $this->setSeoParam('cart', $param);

        return $this->render('carriers', [
                    'positions' => $positions,
                    'cart' => $cart,
                    'shippingAddress' => $shippingAddress,
                    'carriers' => $carriers,
                        //uncomment if needed
//                    'pickUpMessage' => $pickUpMessage,
//                    'selectedCarrier' => $selectedCarrier,
//                    'selectedPickMe' => $selectedPickMe,
//                    'pickMeCode' => $pickme_code
        ]);
    }

    /* STEP 4 - PAYMENT */

    public function actionPayment($placeholder = null) {
        Yii::$app->view->params['noMtree'] = false;
        $cart = Yii::$app->cart;
        $positions = $cart->getPositions();

        if (empty($positions)) {
            return $this->goHome();
        }

        $post = Yii::$app->request->post('Payment');

        // SESSION HANDLER
        if (!empty($post['id_carrier'])) {
            $cart->setCarrier($post['id_carrier']);
            $carrierId = $post['id_carrier'];
        } else if (!empty($cart->getCarrier())) {
            $carrierId = $cart->getCarrier();
        } else {
           /* return LinkHelper::redirect([ '/cart/carrier']);*/
        }

        //uncomment if needed
        // || empty($post['selectedDatePickMe'])
//        if (in_array($carrierId, Yii::$app->params['pickMe']['id_carrier']) && (empty($post['selectedPickMe']) && empty($cart->getPickMe()))) {
//            $errorMessage = Yii::t('frontend/cart', 'Deve selecionar um ponto Chronopost PickUp');
//            Yii::$app->getSession()->setFlash('danger', [
//                'status' => 'danger',
//                'message' => $errorMessage
//            ]);
//
//            return LinkHelper::redirect(['/cart/carrier']);
//        }
//
//        $pickMe = $onlyPickMe = false;
//        $selectedPickMe = 0;
//
//        if (!empty($post['selectedPickMe'])) {
//            $cart->setPickMe($post['selectedPickMe']);
//            $selectedPickMe = $post['selectedPickMe'];
//        } else if (!empty($cart->getPickMe())) {
//            $selectedPickMe = $cart->getPickMe();
//        }
//        if (!empty($selectedPickMe)) {
//            $pickMe = true;
//            $pickmepoint = CarriersPickme::find()->where('pickme_code = :code', [':code' => $selectedPickMe])->one();
//            if (!$pickmepoint) {
//                $errorMessage = Yii::t('frontend/cart', 'O ponto PickUp não existe');
//                Yii::$app->getSession()->setFlash('danger', [
//                    'status' => 'danger',
//                    'message' => $errorMessage
//                ]);
//
//                return LinkHelper::redirect(['/cart/carrier']);
//            }
//            $cart->storeAddress($pickmepoint, 'pickme');
//            $cart->setPickMe($selectedPickMe);
//            $onlyPickMe = true;
//            if (Yii::$app->params['pickMe']['pickMeOnly'] != $carrierId) {
//                $onlyPickMe = false;
//                $address = $cart->getAddress('shipping');
//
//                if (!$address) {
//                    return LinkHelper::redirect(['/cart/shipping']);
//                }
//            }
//        } else {
        $address = $cart->getAddress('shipping');
        /*if (!$address) {
            return LinkHelper::redirect(['/cart/shipping']);
        }*/
//        }
        //uncomment if needed
//        if (!empty($selectedPickMe) && $onlyPickMe == true) {
//
//            // >= 9 ? Ilhas : Portugal Continental
//            $id_country_zone = $cart->getAddress('pickme')->postal_code{0} >= 9 ? 6 : 5;
//            $carrierInfo = Carriers::findOne($carrierId);
//            $shippingCountryZone = CarriersShippingCountryZones::getCountryZoneByCarrier($carrierId, $id_country_zone, true);
//
//            if (is_null($shippingCountryZone)) {
//                return LinkHelper::redirect(['/cart/shipping']);
//            }
//
//            $carrierRates = CarriersRates::getRates($shippingCountryZone->id_carrier_country_zone, $cart->getWeight(), $cart->getCost(true));
//            $carrierRate = CarriersRates::getCartFinalCost(Yii::$app->cart->getCost(true), $carrierInfo, $carrierRates, $shippingCountryZone->id_shipping_country_zone);
//        } else {
        /*$carrierRate = $cart->getAddress('shipping')->getCarrierRate($carrierId, $cart->getWeight(), $cart->getCost(true));*/
//        }

       /* if (!$carrierRate) {
            return $this->goHome();
        }*/

        $cartCosts = [];
        $cartCosts['finalCartCost'] = 0;/*carrierRate['finalCartCost'];*/
        $cartCosts['shippingCost'] = 0;/*$carrierRate['shippingCost'];*/
        $cartCosts['freeShipping'] = 0;/*$carrierRate['freeShipping'];*/

        //seo
        $param = [ 'title' => Yii::t('frontend/customer', 'Escolher Método de Pagamento'),
            'description' => '',
            'keywords' => '',
        ];
        $this->setSeoParam('cart', $param);

        return $this->render('payment', [
                    'positions' => $positions,
                    'cart' => $cart,
                    'cartCosts' => $cartCosts
        ]);
    }

    /* STEP 5 - CONFIRM */

    public function actionConfirm($placeholder = null) {
        $cart = Yii::$app->cart;
        $positions = $cart->getPositions();

        if (empty($positions)) {
            return $this->goHome();
        }

        $post = Yii::$app->request->post();

        // SESSION HANDLER
        if (!empty($post['payment'])) {
            $cart->setPaymentMethod($post['payment']);
            $paymentMethodId = $post['payment'];
        } else if (!empty($cart->getCarrier())) {
            $paymentMethodId = $cart->getPaymentMethod();
        } else {
            return LinkHelper::redirect(['/cart/payment']);
        }

        $paymentMethod = PaymentMethods::findOne($paymentMethodId);
        $paymentMethodsTypes = PaymentHelper::listPaymentTypes();

        if (empty($paymentMethod)) {
            return LinkHelper::redirect(['/cart/payment']);
        }

        //uncomment if needed
//        $pickMe = $onlyPickMe = false;
        $idCarrier = $cart->getCarrier();
//        if (isset(Yii::$app->params['pickMe']['pickMeOnly']) && Yii::$app->params['pickMe']['pickMeOnly'] == $idCarrier) {
//            $onlyPickMe = true;
//        }
//
//        $selectedPickMe = $cart->getPickMe();
//
        if (!empty($idCarrier)) {
            $carrierInfo = Carriers::findOne($idCarrier);
//            if (!empty($selectedPickMe)) {
//                $pickMe = true;
//
//                $address_postal_code = $onlyPickMe == true ? $cart->getAddress('pickme')->postal_code : $cart->getAddress()->postal_code;
//
//                // >= 9 ? Ilhas : Portugal Continental;
//                $id_country_zone = $address_postal_code{0} >= 9 ? 6 : 5;
//                $shippingCountryZone = CarriersShippingCountryZones::getCountryZoneByCarrier($idCarrier, $id_country_zone, true);
//
//                if (is_null($shippingCountryZone)) {
//                    return LinkHelper::redirect(['/cart/shipping']);
//                }
//
//                $carrierRates = CarriersRates::getRates($shippingCountryZone->id_carrier_country_zone, $cart->getWeight(), $cart->getCost(true));
//                $carrierRate = CarriersRates::getCartFinalCost(Yii::$app->cart->getCost(true), $carrierInfo, $carrierRates, $shippingCountryZone->id_shipping_country_zone);
//            } else {
            $carrierRate = $cart->getAddress('shipping')->getCarrierRate($idCarrier, $cart->getWeight(), $cart->getCost(true));
//            }
        }
        if (!$carrierRate) {
            return $this->goHome();
        }

        $cartCosts = [];
        $cartCosts['finalCartCost'] = $carrierRate['finalCartCost'];
        $cartCosts['shippingCost'] = $carrierRate['shippingCost'];
        $cartCosts['freeShipping'] = $carrierRate['freeShipping'];

        $shippingAddress = $cart->getAddress();
        $billingAddress = $cart->getAddress('billing');

        //seo
        $param = [ 'title' => Yii::t('frontend/customer', 'Confirmar Encomenda'),
            'description' => '',
            'keywords' => '',
        ];
        $this->setSeoParam('cart', $param);

        return $this->render('confirm', [
                    'positions' => $positions,
                    'cart' => $cart,
                    'cartCosts' => $cartCosts,
                    'carrierInfo' => $carrierInfo,
                    'paymentMethod' => $paymentMethod,
                    'paymentMethodsTypes' => $paymentMethodsTypes,
                    'shippingAddress' => $shippingAddress,
                    'billingAddress' => $billingAddress,
                        //uncomment if needed
//                    'pickMe' => $pickMe,
//                    'onlyPickMe' => $onlyPickMe,
//                    'selectedPickMe' => $selectedPickMe,
        ]);
    }

    /* AJAX */

    public function actionGetcarriers() {
        if (Yii::$app->request->isAjax) {
            $id_country_zone = Yii::$app->request->get('id_shipping_country_zone');
            $cart = Yii::$app->cart;
            if (!empty($id_country_zone)) {
                $shippingCountryZones = ShippingCountryZones::find()->byId($id_country_zone)->one();
                if ($shippingCountryZones) {
                    $carriers = $shippingCountryZones->getAvailableCarriers($cart->getWeight(), $cart->getCost(true));
                    if ($carriers) {
                        $carriersList = [];
                        foreach ($carriers as $carrier) {
                            $carriersList[$carrier->id_carrier] = $carrier->title;
                        }
                        echo Json::encode(['status' => 'ok', 'carriers' => $carriersList]);
                        exit;
                    }
                }
            } else {
                $id_customer_address = Yii::$app->request->get('id_customer_address');
                $customerAddress = CustomersAddresses::findOne($id_customer_address);
                if ($customerAddress) {
                    $carriers = $customerAddress->getAvailableCarriers($cart->getWeight(), $cart->getCost(true));
                    if ($carriers) {
                        $carriersList = [];
                        foreach ($carriers as $carrier) {
                            $carriersList[$carrier->id_carrier] = $carrier->title;
                        }
                        echo Json::encode(['status' => 'ok', 'carriers' => $carriersList]);
                        exit;
                    }
                }
            }
        }
        echo Json::encode(['status' => 'fail']);
        exit;
    }

    public function actionSimulatecartcost() {
        if (Yii::$app->request->isAjax) {
            $id_carrier = Yii::$app->request->get('id_carrier');
            $id_country_zone = Yii::$app->request->get('id_shipping_country_zone');

            if (!empty($id_country_zone)) {
                $carrierInfo = Carriers::findOne($id_carrier);
                $shippingCountryZone = CarriersShippingCountryZones::getCountryZoneByCarrier($id_carrier, $id_country_zone);
                $carrierRate = CarriersRates::getRates($shippingCountryZone->id_carrier_country_zone, Yii::$app->cart->getWeight(), Yii::$app->cart->getCost(true));
                $cartFinalCosts = CarriersRates::getCartFinalCost(Yii::$app->cart->getCost(true), $carrierInfo, $carrierRate);
            } elseif (!empty(Yii::$app->request->get('id_customer_address'))) {
                $id_customer_address = Yii::$app->request->get('id_customer_address');
                $customerAddress = CustomersAddresses::findOne($id_customer_address);
                if ($customerAddress) {
                    $cartFinalCosts = $customerAddress->getCarrierRate($id_carrier, Yii::$app->cart->getWeight(), Yii::$app->cart->getCost(true));
                }
            } else {
                echo Json::encode(['status' => 'fail']);
                exit;
            }

            $return['status'] = 'ok';
            $return['finalCartCost'] = FormatterHelper::displayNumber($cartFinalCosts['finalCartCost']);
            $return['shippingCost'] = FormatterHelper::displayNumber($cartFinalCosts['shippingCost']);
            $return['freeShipping'] = ($cartFinalCosts['freeShipping'] ? 1 : 0);

            echo Json::encode($return);
            exit;
        }
        echo Json::encode([

            'status' => 'fail']);
        exit;
    }

    public function actionSearchpickme() {
        if (Yii::$app->request->isAjax && !empty(Yii::$app->request->get('q'))) {
            $q = Yii::$app->request->get('q');
            $pickmePoints = CarriersPickme::find()->select('pickme_code, name, address, location, postal_code')
                    ->distinct()
                    ->orWhere(['like', 'name', $q])
                    ->orWhere(['like', 'address', $q])
                    ->orWhere(['like', 'location', $q])
                    ->orWhere(['like', 'postal_code', $q])
                    ->orderBy('name')
                    ->asArray()
                    ->all();
            echo Json::encode($pickmePoints);
            exit;
        }
        echo Json::encode(['status' => 'fail']);
        exit;
    }

    public function actionAddtocart() {
        if (Yii::$app->request->isAjax) {
            $cartProducts = Yii::$app->request->get('CartProduct');

            $overrideQuantities = true;

            $nostockText = Yii::t('frontend/product', "Sem Stock");
            if (count($cartProducts) > 0) {
                $product = Products::find()->byId($cartProducts['id_product'])->one();

                if ($product->handle_stock == 1) {

                    switch ($product->type_of_product) {
                        case 'product':
                            if (!empty($cartProducts['combination']) && $cartProducts['id_combination'] > 0) {
                                $id_combination = (int) $cartProducts['id_combination'];
                                $stock = Stock::find()->where('id_warehouse=:id_warehouse AND id_product=:id_product AND id_combination=:id_combination', [ ':id_warehouse' => $cartProducts['warehouse'], ':id_product' => $cartProducts['id_product'], ':id_combination' => $cartProducts['id_combination']])->one();
                                if (!$stock) {
                                    echo Json::encode(['status' => 'fail', 'msg' => $nostockText]);
                                    exit;
                                } elseif ($stock->available_stock < $cartProducts['qty']) {
                                    echo Json::encode(['status' => 'fail', 'msg' => $nostockText]);
                                    exit;
                                }
                                $product->setCombination($id_combination);
                            } else {
                                $stock = Stock::find()->where('id_warehouse=:id_warehouse AND id_product=:id_product', [ ':id_warehouse' => $cartProducts['warehouse'], ':id_product' => $cartProducts['id_product']])->one();
                                if (!$stock) {
                                    echo Json::encode(['status' => 'fail', 'msg' => $nostockText]);
                                    exit;
                                } elseif ($stock->available_stock < $cartProducts['qty']) {
                                    echo Json::encode(['status' => 'fail', 'msg' => $nostockText]);
                                    exit;
                                }
                            }
                            break;
                        case 'pack': $productsInPack = $product->getProductsPacks()->all();

                            foreach ($productsInPack as $productInPack) {
                                $productModel = Products::find()->byId($productInPack->id_product_item)->one();
                                if ($productModel->handle_stock == 1) {
                                    if ($productInPack->id_combination > 0)
                                        $stock = Stock::find()->where('id_product=:id_product AND id_combination=:id_combination', [ 'id_product' => $productInPack->id_product_item, 'id_combination' => $productInPack->id_combination])->one();
                                    else
                                        $stock = Stock::find()->where('id_product=:id_product AND id_combination IS NULL', [':id_product' => $productInPack->id_product_item])->one();

                                    if (!$stock) {
                                        echo Json::encode(['status' => 'fail', 'msg' => $nostockText]);
                                        exit;
                                    } elseif ($stock->available_stock < ($productInPack->qty * $cartProducts['qty'])) {
                                        echo Json::encode(['status' => 'fail', 'msg' => $nostockText]);
                                        exit;
                                    }
                                }
                            }
                            break;
                    }
                }

                $cart = Yii::$app->cart;

                if ($overrideQuantities || !$cart->hasPosition($product->getIndex())) {
                    $cart->put($product, $cartProducts['qty']);
                }

                $total = $cart->getTotalPositions();

                $cartHtml = $this->renderAjax('/cart/mini_cart');

                echo Json::encode(['status' => 'ok', 'html' => $cartHtml, 'total' => $total]);
            } else {
                echo Json::encode(['status' => 'fail', 'msg' => 'get error']);
            }
            exit;
        }
        exit;
    }

    public function actionHandleVoucher() {
        if (Yii::$app->request->isAjax) {
            $cart = Yii::$app->cart;
            $voucherCode = Yii::$app->request->get('voucher');

            $cart->applyVoucher($voucherCode);

            $totalHtml = $this->renderAjax('/cart/_total');
            $voucherApplied['html'] = $totalHtml;

            echo Json::encode($voucherApplied);
            exit;
        }
        exit;
    }

    private function getSelectedPickMe($pickme_code = '') {
        $pickmeJs = '{}';
        if (!empty($pickme_code)) {
            $pickme = CarriersPickme::find()->where('pickme_code LIKE :pickme_code', [':pickme_code' => $pickme_code])->one();
            if (!empty($pickme)) {
                $pickmeJs = "{pickme_code: '" . $pickme->pickme_code . "', name: '" . $pickme->name . "', address: '" . $pickme->address . "', postal_code: '" . $pickme->ostal_code . "', location: '" . $pickme->location . "'}";
            }
        }
        return $pickmeJs;
    }

}
