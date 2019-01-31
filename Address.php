<?php
class Address extends AddressCore
{
    /* Normally, in Prestashop, an used address is never deleted, is just marked as $address->deleted = 1 and updated
    ** To check if an address is being used, only the table ps_orders is checked by default. This causes error in AdminCartController
    ** if there is any cart with a deleted address, because ps_cart is not being checked. With this override, now it is
    */
    
    public function isUsed()
    {
        if ((int) $this->id <= 0) {
            return false;
        }
        $result = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
		SELECT COUNT(`id_order`) AS used
		FROM `' . _DB_PREFIX_ . 'orders`
		WHERE `id_address_delivery` = ' . (int) $this->id . '
		OR `id_address_invoice` = ' . (int) $this->id);
        if ($result > 0) {
            return $result;
        } else {
            $result_cart = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
        SELECT COUNT(`id_cart`) AS used
        FROM `' . _DB_PREFIX_ . 'cart`
        WHERE `id_address_delivery` = ' . (int) $this->id . '
        OR `id_address_invoice` = ' . (int) $this->id);
            if ($result_cart > 0) {
                return $result_cart;
            } else {
                return false;
            }
        }
    }
}
