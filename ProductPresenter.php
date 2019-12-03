protected function shouldEnableAddToCartButton(array $product, ProductPresentationSettings $settings)
    {
        if (($product['customizable'] == 2 || !empty($product['customization_required']))) {
            $shouldEnable = false;

            if (isset($product['customizations'])) {
                $shouldEnable = true;
                foreach ($product['customizations']['fields'] as $field) {
                    if ($field['required'] && !$field['is_customized']) {
                        $shouldEnable = false;
                    }
                }
            }
        } else {
            $shouldEnable = true;
        }

        $shouldEnable = $shouldEnable && $this->shouldShowAddToCartButton($product);

        /*if ($settings->stock_management_enabled
            && !$product['allow_oosp']
            && $product['quantity'] <= 0
        ) {
            $shouldEnable = false;
        }
        if ($settings->stock_management_enabled
            && !$product['allow_oosp']
            && $product['quantity'] <= 0
        ) {
            $shouldEnable = false;
        }

        return $shouldEnable;
        */
        /* override georgeb */
        $qty_in_cart = 0;
        $products = \Context::getContext()->cart->getProducts();
        if ($products) {
            foreach ($products as $prod) {
                if ($prod['id_product'] == $product['id_product'] && $prod['id_product_attribute'] == $product['id_product_attribute']) {
                    $qty_in_cart += $prod['cart_quantity'];
                }
            }
        }
        
        $product['quantity_wanted'] = (int) Tools::getValue('quantity_wanted', 1);
        
        if ($settings->stock_management_enabled
            && !$product['allow_oosp']
            && $product['quantity'] < ($qty_in_cart + $product['quantity_wanted'])
        ) {
            $shouldEnable = false;
        }
        
        return $shouldEnable;
        /* end override georgeb */
    }
