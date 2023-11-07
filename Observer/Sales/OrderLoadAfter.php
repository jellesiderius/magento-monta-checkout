<?php

namespace Montapacking\MontaCheckout\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderExtension;
use Magento\Checkout\Model\Session as CheckoutSession;

class OrderLoadAfter implements ObserverInterface
{
    private $orderExtension;

    protected $checkoutSession;

    /**
     * OrderLoadAfter constructor.
     *
     */
    public function __construct(
        OrderExtension $orderExtension,
        CheckoutSession $checkoutSession
    )
    {
        $this->orderExtension = $orderExtension;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute(Observer $observer)
    {
        $deliverySession = isset($this->checkoutSession->getData()['latest_shipping'][0]) ? $this->checkoutSession->getData()['latest_shipping'][0] : [];
        $pickupSession = isset($this->checkoutSession->getData()['latest_shipping'][1]) ? $this->checkoutSession->getData()['latest_shipping'][1] : [];

        $firstDeliveryCode = null;
        $deliveryValid = false;
        $pickupValid = false;

        $order = $observer->getOrder();

        $extensionAttributes = $order->getExtensionAttributes();

        if ($extensionAttributes === null) {
            $extensionAttributes = $this->orderExtension;
        }

        $attr = $order->getData('montapacking_montacheckout_data');
        if($attr != null) {
            $attr_obj = json_decode($attr);

            // user input valideren
            $shippersOptionst = array();
            $shipperOption = null;
            foreach($deliverySession as $delivery){
                foreach($delivery->options as $optiontt){
                    array_push($shippersOptionst, $optiontt->name);
                    if($optiontt->name === $attr_obj->additional_info[0]->name and $optiontt->code === $attr_obj->additional_info[0]->code){
                        $shipperOption = $optiontt;
                    }
                }
            }

            $additional_info = $attr_obj->additional_info[0];
            if (count($deliverySession) > 0) 
            {
                if (!in_array($additional_info->name, $shippersOptionst)){
                    die("shipper name not valid");
                }
                if($additional_info->date !== $shipperOption->date){
                    die("shipping date not valid");
                }
            }
            // end validatie
            
            foreach($deliverySession as $key => $deliverySessionItem) {
                foreach($deliverySessionItem->options as $option) {
                    if($key == 0)  {
                        // It is the first delivery item. Save the code as backup
                        $firstDeliveryCode = $option->code;
                    }
                    if($attr_obj->additional_info[0]->code == $option->code) {
                        $deliveryValid = true;
                        $extensionAttributes->setMontapackingMontacheckoutData($attr);
                        $order->setExtensionAttributes($extensionAttributes);
                    }
                }
            }

            foreach($pickupSession as $key => $pickupSessionItem) {
                foreach($pickupSessionItem->options as $option) {
                    if($attr_obj->additional_info[0]->code == $option->code) {
                        $pickupValid = true;
                        $extensionAttributes->setMontapackingMontacheckoutData($attr);
                        $order->setExtensionAttributes($extensionAttributes);
                    }
                }
            }

            // The code has been tempered with. Give a fallback code before pushing the data to the Monta Services
            if(!$deliveryValid || !$pickupValid) {
                $attr_obj->additional_info[0]->code = $firstDeliveryCode;
                $attr = json_encode($attr_obj);

                $extensionAttributes->setMontapackingMontacheckoutData($attr);
                $order->setExtensionAttributes($extensionAttributes);
            }
        }
    }
}
