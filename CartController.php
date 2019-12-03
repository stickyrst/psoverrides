<?php
/* You have to change some variables to public, in the main file, and also apply modifications in /src/Core/Product/ProductPresenter.php */
class CartController extends CartControllerCore
{
    private function shouldAvailabilityErrorBeRaisedOverride($product, $qtyToCheck)
    {
        if (($this->id_product_attribute)) {
            return (!Product::isAvailableWhenOutOfStock($product->out_of_stock)
                && !Attribute::checkAttributeQty($this->id_product_attribute, $qtyToCheck));
        } elseif (Product::isAvailableWhenOutOfStock($product->out_of_stock)) {
            return false;
        }

        // product quantity is the available quantity after decreasing products in cart
        $productQuantity = StockAvailable::getQuantityAvailableByProduct($this->id_product, $this->id_product_attribute);
        
        if ($productQuantity < $qtyToCheck) {
            return true;
        }
        return false;
    }
    
    public function productInCartMatchesCriteriaOverride($productInCart)
    {
        return (
            !isset($this->id_product_attribute) ||
            (
                $productInCart['id_product_attribute'] == $this->id_product_attribute
            )
        ) && isset($this->id_product) && $productInCart['id_product'] == $this->id_product;
    }
    
    protected function processChangeProductInCart()
    {
        $mode = (Tools::getIsset('update') && $this->id_product) ? 'update' : 'add';
        $ErrorKey = ('update' === $mode) ? 'updateOperationError' : 'errors';

        if (Tools::getIsset('group')) {
            $this->id_product_attribute = (int)Product::getIdProductAttributesByIdAttributes(
                $this->id_product,
                Tools::getValue('group')
            );
        }

        if ($this->qty == 0) {
            array_push(
                $this->{$ErrorKey},
                $this->trans(
                    'Null quantity.',
                    array(),
                    'Shop.Notifications.Error'
                )
            );
        } elseif (!$this->id_product) {
            array_push(
                $this->{$ErrorKey},
                $this->trans(
                    'Product not found',
                    array(),
                    'Shop.Notifications.Error'
                )
            );
        }

        $product = new Product($this->id_product, true, $this->context->language->id);
        if (!$product->id || !$product->active || !$product->checkAccess($this->context->cart->id_customer)) {
            array_push(
                $this->{$ErrorKey},
                $this->trans(
                    'This product (%product%) is no longer available.',
                    array('%product%' => $product->name),
                    'Shop.Notifications.Error'
                )
            );
            return;
        }

        if (!$this->id_product_attribute && $product->hasAttributes()) {
            $minimum_quantity = ($product->out_of_stock == 2)
                ? !Configuration::get('PS_ORDER_OUT_OF_STOCK')
                : !$product->out_of_stock;
            $this->id_product_attribute = Product::getDefaultAttribute($product->id, $minimum_quantity);
            // @todo do something better than a redirect admin !!
            if (!$this->id_product_attribute) {
                Tools::redirectAdmin($this->context->link->getProductLink($product));
            }
        }

        $qty_to_check = $this->qty;
        $cart_products = $this->context->cart->getProducts();
        
        /* override georgeb */
        
        if (is_array($cart_products)) {
            $qty_to_check_override = 0;
            foreach ($cart_products as $cart_product) {
                if ($this->productInCartMatchesCriteriaOverride($cart_product)) {
                    $qty_to_check_override += $cart_product['cart_quantity'];
                }
            }
            if (Tools::getValue('op', 'up') == 'down') {
                $qty_to_check_override -= $this->qty;
            } else {
                $qty_to_check_override += $this->qty;
            }
        }
        
        if ($this->shouldAvailabilityErrorBeRaisedOverride($product, $qty_to_check_override)) {
            array_push(
                $this->{$ErrorKey},
                $this->trans(
                    'The item %product% in your cart is no longer available in this quantity. You cannot proceed with your order until the quantity is adjusted.',
                    array('%product%' => $product->name),
                    'Shop.Notifications.Error'
                )
            );
        }
        
        /* end override georgeb */

        if (is_array($cart_products)) {
            foreach ($cart_products as $cart_product) {
                if ($this->productInCartMatchesCriteria($cart_product)) {
                    $qty_to_check = $cart_product['cart_quantity'];

                    if (Tools::getValue('op', 'up') == 'down') {
                        $qty_to_check -= $this->qty;
                    } else {
                        $qty_to_check += $this->qty;
                    }

                    break;
                }
            }
        }

        // Check product quantity availability
        if ('update' !== $mode && $this->shouldAvailabilityErrorBeRaised($product, $qty_to_check)) {
            array_push(
                $this->{$ErrorKey},
                $this->trans(
                    'The item %product% in your cart is no longer available in this quantity. You cannot proceed with your order until the quantity is adjusted.',
                    array('%product%' => $product->name),
                    'Shop.Notifications.Error'
                )
            );
        }

        // If no errors, process product addition
        if (!$this->errors) {
            // Add cart if no cart found
            if (!$this->context->cart->id) {
                if (Context::getContext()->cookie->id_guest) {
                    $guest = new Guest(Context::getContext()->cookie->id_guest);
                    $this->context->cart->mobile_theme = $guest->mobile_theme;
                }
                $this->context->cart->add();
                if ($this->context->cart->id) {
                    $this->context->cookie->id_cart = (int)$this->context->cart->id;
                }
            }

            // Check customizable fields

            if (!$product->hasAllRequiredCustomizableFields() && !$this->customization_id) {
                array_push(
                    $this->{$ErrorKey},
                    $this->trans(
                        'Please fill in all of the required fields, and then save your customizations.',
                        array(),
                        'Shop.Notifications.Error'
                    )
                );
            }

            if (!$this->errors) {
                $cart_rules = $this->context->cart->getCartRules();
                $available_cart_rules = CartRule::getCustomerCartRules(
                    $this->context->language->id,
                    (isset($this->context->customer->id) ? $this->context->customer->id : 0),
                    true,
                    true,
                    true,
                    $this->context->cart,
                    false,
                    true
                );
                $update_quantity = $this->context->cart->updateQty(
                    $this->qty,
                    $this->id_product,
                    $this->id_product_attribute,
                    $this->customization_id,
                    Tools::getValue('op', 'up'),
                    $this->id_address_delivery,
                    null,
                    true,
                    true
                );
                if ($update_quantity < 0) {
                    // If product has attribute, minimal quantity is set with minimal quantity of attribute
                    $minimal_quantity = ($this->id_product_attribute)
                        ? Attribute::getAttributeMinimalQty($this->id_product_attribute)
                        : $product->minimal_quantity;
                    array_push(
                        $this->{$ErrorKey},
                        $this->trans(
                            'You must add %quantity% minimum quantity',
                            array('%quantity%' => $minimal_quantity),
                            'Shop.Notifications.Error'
                        )
                    );
                } elseif (!$update_quantity) {
                    array_push(
                        $this->errors,
                        $this->trans(
                            'You already have the maximum quantity available for this product.',
                            array(),
                            'Shop.Notifications.Error'
                        )
                    );
                } elseif ($this->shouldAvailabilityErrorBeRaised($product, $qty_to_check)) {
                    // check quantity after cart quantity update
                    array_push(
                        $this->{$ErrorKey},
                        $this->trans(
                            'The item %product% in your cart is no longer available in this quantity. You cannot proceed with your order until the quantity is adjusted.',
                            array('%product%' => $product->name),
                            'Shop.Notifications.Error'
                        )
                    );
                }

            }
        }

        $removed = CartRule::autoRemoveFromCart();
        CartRule::autoAddToCart();
    }
}
