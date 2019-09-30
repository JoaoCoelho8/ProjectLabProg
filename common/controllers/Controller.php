<?php

namespace common\controllers;

use backend\components\language\helpers\Language;
use common\models\customers\Customers;
use common\models\customers\Usersonline;
use common\models\media\Media;
use Yii;
use yii\helpers\Html;
use yii\web\Controller as BaseController;
use yii\web\Cookie;
use yii\web\Session;

/**
 * Global controller used to register and translate from JavaScript files.
 */
class Controller extends BaseController {

    public $localizedLang = null;
    public $allowCookies = false;

    /**
     * Method used to register JavaScript in order to translate them.
     */
    public function init() {
        Language::registerAssets();
        parent::init();
    }

    public function afterAction($action, $result) {
        $result = parent::afterAction($action, $result);
        $flag = true;
        if (Yii::$app->controller->id == "site" && Yii::$app->controller->action->id == "error")
            $flag = false;

        if ($flag) {
            Yii::$app->cart->saveToSession(true);
        }

        return $result;
    }

    public function beforeAction($event) {
        $event = parent::beforeAction($event);
        $get = Yii::$app->request->get();

        if (!empty($get['lang']) && array_key_exists($get['lang'], Yii::$app->params['languages'])) {
            Yii::$app->language = $get['lang'];
        }

        // outdated users (more than 15 minutes old):
        $time = time();
        $time_check = $time - 900;
        Usersonline::deleteAll("time < $time_check");
        // check/register online user:
        $session = new Session;
        $session->open();
        $sessionid = $session->id;
        $useronline = Usersonline::findOne($sessionid);
        if ($useronline) {
            $useronline->time = $time;
            $useronline->save();
        } else {
            $useronline = new Usersonline;
            $useronline->sessionid = $sessionid;
            $useronline->time = $time;
            $useronline->save();
        }


        $cookies = Yii::$app->request->cookies;
        $this->allowCookies = $cookies->getValue('allowCookies');
        if (!Yii::$app->user->isGuest) {
            $user = Customers::findOne(Yii::$app->user->identity->id_customer);
            if ($user->is_cookiesallowed == 1) {
                $cookiesSet = Yii::$app->response->cookies;
                $cookiesSet->add(new Cookie([
                    'name' => 'allowCookies',
                    'value' => true,
                ]));
                $this->allowCookies = true;
            }
        }

        return $event;
    }

    public function setLocalizedLang($lang) {
        $this->localizedLang = $lang;
    }

    public function getLocalizedLang() {
        return ($this->localizedLang ? $this->localizedLang : Yii::$app->params['defaultLanguage']);
    }

    public function outputPageSeo($seoParams = null) {
        $return = "";

        if (isset($seoParams)) {
            $title = "";
            // Global
            if (isset($seoParams['global'])) {
                $title = $seoParams['global']['title'];
                $description = $seoParams['global']['description'];
                $keywords = $seoParams['global']['keywords'];
            }
            // Prod
            if (isset($seoParams['product'])) {
                $title = $seoParams['product']['title'];
                $description = $seoParams['product']['description'];
                $keywords = $seoParams['product']['keywords'];
            }
            // Cat
            if (isset($seoParams['category'])) {
                $title = $seoParams['category']['title'];
                $description = $seoParams['category']['description'];
                $keywords = $seoParams['category']['keywords'];
            }
            // Prod & Cat
            if (isset($seoParams['product']) && isset($seoParams['category'])) {
                $title = $seoParams['product']['title'] . " | " . $seoParams['category']['title'];
                $description = $seoParams['product']['description'];
                $keywords = $seoParams['product']['keywords'];
                $image = !empty($seoParams['product']['image']) ? $seoParams['product']['image'] : '';
            }

            $title .= " | " . Yii::$app->params['mainConfiguration']['storeName'];

            if (!empty($title))
                $return .= '<title>' . Html::encode($title) . '</title>';

            // Metas
            $return .= '<!-- Metas -->';
            $return .= '<meta name="author" content="Loja.com" />';

            if (!empty($description)) {
                $return .= '<meta name="description" content="' . Html::encode($description) . '" />';
            }

            if (!empty($image)) {
                $return .= '<meta property="og:image" content="' . $image . '" />';
            }

            if (!empty($keywords)) {
                $return .= '<meta name="keywords" content="' . Html::encode($keywords) . '" />';
            }
            $return .= '<meta name="revisit-after" content="3 days" />';
            $return .= '<meta name="robots" content="all" />';


            // OpenGraph
            $return .= '<!-- OpenGraph -->';
            if (!empty($title)) {
                $return .= '<meta property="og:title" content="' . $title . '" />';
            }
            if (!empty($description)) {
                $return .= '<meta property="og:description" content="' . Html::encode($description) . '" />';
            }
            $return .= '<meta property="og:type" content="company" />';

            $return .= '<meta property="og:url" content="' . Yii::$app->request->absoluteUrl . '" />';
            /*
              $return = <<<EOT
              <meta property="og:image" content="" />
              <meta property="og:site_name" content="" />
              EOT;

             */
            return $return;
        }
    }

    public function setSeoPage($area, $model) {
        if (!isset(Yii::$app->view->params['seoPage'])) {
            Yii::$app->view->params['seoPage'] = [];
        }
        $seoPage = Yii::$app->view->params['seoPage'];
        if ($model)
            $seoPage[$area] = [
                'title' => $model->meta_title,
                'description' => $model->meta_description,
                'keywords' => $model->meta_keywords,
                'alias' => $model->alias
            ];
        Yii::$app->view->params['seoPage'] = $seoPage;
    }

    public function setSeoImage($area, $model, $image = null) {
        if (!isset(Yii::$app->view->params['seoPage'])) {
            Yii::$app->view->params['seoPage'] = [];
        }

        $seoPage = Yii::$app->view->params['seoPage'];
        if (!is_null($image)) {
            $seoPage[$area]['image'] = $image;
            Yii::$app->view->params['seoPage'] = $seoPage;
        } else {
            $singleImage = Media::getMedia($model, 1, Yii::$app->language, Media::MEDIA_ARRAY);
            if (!empty($singleImage[0]['item'])) {

                //Check if an image was found.
                $seoPage[$area]['image'] = $singleImage[0]['item'];

                Yii::$app->view->params['seoPage'] = $seoPage;
            } else {
                $seoPage[$area]['image'] = Yii::$app->baseUrl . (!empty(Yii::$app->params['facebookDefaultImage'])) ? Yii::$app->params['facebookDefaultImage'] : '';
                Yii::$app->view->params['seoPage'] = $seoPage;
            }
        }
    }

    public function setSeoParam($area, $param, $value = null) {
        if (!isset(Yii::$app->view->params['seoPage'])) {
            Yii::$app->view->params['seoPage'] = [];
        }
        if (is_array($param)) {
            $section[$area] = $param;
            Yii::$app->view->params['seoPage'] = array_merge(Yii::$app->view->params['seoPage'], $section);
        } else {
            $seoPage = [];
            $seoPage[$area][$param] = $value;
            Yii::$app->view->params['seoPage'] = array_merge(Yii::$app->view->params['seoPage'], $seoPage);
        }
    }

    public function setEcommerceTracking($action = 'detail', $detailData = NULL, $needsSend = FALSE) {
        $gaCode = "ga('require', 'ec');";
        $gaCode .= "ga('set', '&cu', 'EUR');";
        switch ($action) {
            case 'detail':
                $id = $detailData['id'];
                $name = $detailData['title'];
                $price = $detailData['price'];
                $category = $detailData['category'];
                $tax = $detailData['tax'];
                $price = $price - $tax;
                $gaCode .= <<<EOT
                    ga('ec:addProduct', {
                        'id': '$id',
                        'name': '$name',
                        'price': '$price',
                        'category': '$category',
                        'tax': '$tax'
                    });
                    ga('ec:setAction', 'detail');
EOT;
                break;
            case 'search':
                $id = $detailData['id'];
                $name = $detailData['title'];
                $category = $detailData['category'];
                //$brand = $detailData['brand'];
                $position = $detailData['position'];
                $gaCode .= <<<EOT
                    ga('ec:addImpression', {
                        'id': '$id',
                        'name': '$name',
                       // 'brand': '$brand',
                        'category': '$category',
                        'list': 'Pesquisa',
                        'position': $position
                    });
EOT;
                break;
            case 'add':
                $id = $detailData['id'];
                $name = $detailData['title'];
                $price = $detailData['price'];
                $category = $detailData['category'];
                $tax = $detailData['tax'];
                $price = $price - $tax;
                $gaCode .= <<<EOT
                    ga('ec:addProduct', {
                        'id': '$id',
                        'name': '$name',
                        'price': '$price',
                        'category': '$category',
                        'tax': '$tax'
                    });
                    ga('ec:setAction', 'add');
EOT;
                break;
            case 'checkout':
                $cart = Yii::$app->cart;
                $positions = $cart->getPositions();
                foreach ($positions as $position) {
                    $cost = $position->getCost(true);
                    $quantity = $position->getQuantity();
                    $category = $position->getCategory();
                    $category = $category->name;
                    $name = $position->title;
                    $id = $position->reference;
                    $taxRate = $position->tax->tax;
                    if (!empty($taxRate)) {
                        $tax = $position->getCost() - ( $position->getCost() / (($taxRate / 100) + 1) );
                    } else
                        $tax = '0.00';

                    $cost = $cost - $tax;

                    $gaCode .= <<<EOT

                    ga('ec:addProduct', {
                        'id': '$id',
                        'name': '$name',
                        'category': '$category',
                        'price': '$cost',
                        'quantity': '$quantity',
                        'tax': '$tax'
                    });
EOT;
                }
                $step = $detailData['step'];
                $option = $detailData['option'];

                $multibanco = Yii::t('frontend/orderdetail', 'Multibanco');
                $cartaoCredito = Yii::t('frontend/orderdetail', 'Cartão de Crédito');
                $paypal = Yii::t('frontend/orderdetail', 'Paypal');
                $transferencia = Yii::t('frontend/orderdetail', 'Transferência Bancária');
                $contraReembolso = Yii::t('frontend/orderdetail', 'Contra Reembolso');

                if ($step == 6) {
                    $gaCode .= <<<EOT
                    var paymentType = $(".radiobtn_press.selected > img").prop('alt');
                    switch(paymentType) {
                        case 'cp':
                            label = "$contraReembolso";
                            break;
                        case 'cc':
                            label = "$cartaoCredito";
                            break;
                        case 'wt':
                            label = "$transferencia";
                            break;
                        case 'mb':
                            label = "$multibanco";
                            break;
                        case 'pp':
                            label = "$paypal";
                            break;
                    }
                    ga('ec:setAction','checkout', {
                        'step': $step,
                        'option': label
                    });
EOT;
                } else {
                    $gaCode .= <<<EOT
                    ga('ec:setAction','checkout', {
                        'step': $step,
                        'option': '$option'
                    });
EOT;
                }
                break;
            case 'checkout_option':
                $cart = Yii::$app->cart;
                $positions = $cart->getPositions();
                foreach ($positions as $position) {
                    $cost = $position->getCost(true);
                    $quantity = $position->getQuantity();
                    $category = $position->getCategory();
                    $category = $category->name;
                    $name = $position->title;
                    $id = $position->reference;
                    // new stuff:
                    $taxRate = $position->tax->tax;
                    if (!empty($taxRate)) {
                        $tax = $position->getCost() - ( $position->getCost() / (($taxRate / 100) + 1) );
                    } else
                        $tax = '0.00';
                    $cost = $cost - $tax;
                    $gaCode .= <<<EOT

                    ga('ec:addProduct', {
                        'id': '$id',
                        'name': '$name',
                        'category': '$category',
                        'price': '$cost',
                        'quantity': '$quantity',
                        'tax': '$tax'
                    });
EOT;
                }
                $step = $detailData['step'];
                $option = $detailData['option'];

                $multibanco = Yii::t('frontend/orderdetail', 'Multibanco');
                $cartaoCredito = Yii::t('frontend/orderdetail', 'Cartão de Crédito');
                $paypal = Yii::t('frontend/orderdetail', 'Paypal');
                $transferencia = Yii::t('frontend/orderdetail', 'Transferência Bancária');
                $contraReembolso = Yii::t('frontend/orderdetail', 'Contra Reembolso');

                if ($step == 6) {
                    $gaCode .= <<<EOT
                    var paymentType = $(".radiobtn_press.selected > img").prop('alt');
                    switch(paymentType) {
                        case 'cp':
                            label = "$contraReembolso";
                            break;
                        case 'cc':
                            label = "$cartaoCredito";
                            break;
                        case 'wt':
                            label = "$transferencia";
                            break;
                        case 'mb':
                            label = "$multibanco";
                            break;
                        case 'pp':
                            label = "$paypal";
                            break;
                    }
                    ga('ec:setAction','checkout', {
                        'step': $step,
                        'option': label
                    });
                    ga('send', 'event', 'Checkout', 'Option');
EOT;
                } else {
                    $gaCode .= <<<EOT
                    ga('ec:setAction','checkout', {
                        'step': $step,
                        'option': '$option'
                    });
                    ga('send', 'event', 'Checkout', 'Option');
EOT;
                }
                break;
            case 'purchase':
                //$gaCode .= "ga('set', '&cu', 'EUR');";
                $cart = Yii::$app->cart;
                $positions = $cart->getPositions();
                foreach ($positions as $position) {
                    $cost = $position->getCost(true);
                    $quantity = $position->getQuantity();
                    $category = $position->getCategory();
                    $category = $category->name;
                    $name = $position->title;
                    $id = $position->reference;
                    // new stuff:
                    $taxRate = $position->tax->tax;
                    if (!empty($taxRate)) {
                        $tax = $position->getCost() - ( $position->getCost() / (($taxRate / 100) + 1) );
                    } else
                        $tax = '0.00';
                    $cost = $cost - $tax;
                    $gaCode .= <<<EOT
                    ga('ec:addProduct', {
                        'id': '$id',
                        'name': '$name',
                        'category': '$category',
                        'price': '$cost',
                        'quantity': '$quantity',
                        'tax': '$tax'
                    });
EOT;
                }
                $time = time();
                $produtos = Yii::$app->cart->getCost(true, true);
                $portes = $detailData['shippingcost'];
                $gaCode .= <<<EOT
                    ga('ec:setAction', 'purchase', {
                        id: '$time',
                        revenue: '$produtos',
                        shipping: '$portes'
                    });
                    $("#finishOrderForm").append("<input type='hidden' name='googleEcommerceId' value='$time'/>");
EOT;
                break;
        }
        if ($needsSend)
            $gaCode .= <<<EOT
                    ga('send', 'pageview');
EOT;
        return $gaCode;
    }

}
