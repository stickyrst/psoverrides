<?php
class Tools extends ToolsCore
{
    /* Override - Print the RON currency always with the symbol AFTER the amount */
    public static function displayPrice($price, $currency = null, $no_utf8 = false, Context $context = null)
    {
        if (!is_numeric($price)) {
            return $price;
        }
        if (!$context) {
            $context = Context::getContext();
        }
        if ($currency === null) {
            $currency = $context->currency;
        } elseif (is_int($currency)) {
            $currency = Currency::getCurrencyInstance((int)$currency);
        }
        $old_language = $context->language;
        $context->language = new Language(Language::getIdByIso('RO'));
        $cldr = self::getCldr($context);
        $context->language = $old_language;

        return $cldr->getPrice($price, is_array($currency) ? $currency['iso_code'] : $currency->iso_code);
    }
    
    /* Override - Allow <a href="tel:... in HTML inputs */
    public static function purifyHTML($html, $uri_unescape = null, $allow_style = false)
    {
        $hasTel = strpos($html, 'href="tel:') !== false;
        if($hasTel) $html = str_replace('href="tel:', 'href="tel//:', $html);
        
        $html = parent::purifyHTML($html, $uri_unescape, $allow_style);
        
        // finish markup for things that HTMLPurifier doesn't support
        if($hasTel) $html = str_replace('href="tel//:', 'href="tel:', $html);
        return $html;
    }
}
