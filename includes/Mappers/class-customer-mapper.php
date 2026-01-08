<?php
/**
 * Customer Mapper class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Mappers
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Mappers;

use Jehankandy\SAP_WooCommerce_Sync\Utilities\Helper;

/**
 * Maps WooCommerce customers to SAP Business Partner format.
 *
 * @since 1.0.0
 */
class Customer_Mapper
{

    /**
     * Map WooCommerce order customer data to SAP Business Partner format.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return array<string, mixed> SAP Business Partner data.
     */
    public function map(\WC_Order $order): array
    {
        $customer_id = $order->get_customer_id();
        $card_code = Helper::generate_card_code($customer_id ?: $order->get_id(), 'WC');

        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $company = $order->get_billing_company();

        $card_name = $company ?: trim($first_name . ' ' . $last_name);

        $business_partner = [
            'CardCode' => $card_code,
            'CardName' => $card_name,
            'CardType' => 'cCustomer',
            'EmailAddress' => $order->get_billing_email(),
            'Phone1' => $order->get_billing_phone(),
            'Cellular' => $order->get_billing_phone(),
        ];

        // Add addresses.
        $business_partner['BPAddresses'] = $this->map_addresses($order);

        // Add contact person.
        $business_partner['ContactEmployees'] = $this->map_contacts($order);

        // Add billing address fields directly.
        $business_partner['Address'] = $this->format_address($order, 'billing');
        $business_partner['City'] = $order->get_billing_city();
        $business_partner['Country'] = $order->get_billing_country();
        $business_partner['ZipCode'] = $order->get_billing_postcode();

        // Currency.
        $business_partner['Currency'] = $order->get_currency();

        return apply_filters('sap_wc_sync_mapped_customer', $business_partner, $order);
    }

    /**
     * Map addresses for Business Partner.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return array<int, array<string, mixed>>
     */
    private function map_addresses(\WC_Order $order): array
    {
        $addresses = [];

        // Billing address.
        $addresses[] = [
            'AddressName' => 'BILL',
            'AddressType' => 'bo_BillTo',
            'Street' => $order->get_billing_address_1(),
            'Block' => $order->get_billing_address_2(),
            'City' => $order->get_billing_city(),
            'State' => $order->get_billing_state(),
            'ZipCode' => $order->get_billing_postcode(),
            'Country' => $order->get_billing_country(),
        ];

        // Shipping address (if different).
        if ($order->has_shipping_address()) {
            $addresses[] = [
                'AddressName' => 'SHIP',
                'AddressType' => 'bo_ShipTo',
                'Street' => $order->get_shipping_address_1(),
                'Block' => $order->get_shipping_address_2(),
                'City' => $order->get_shipping_city(),
                'State' => $order->get_shipping_state(),
                'ZipCode' => $order->get_shipping_postcode(),
                'Country' => $order->get_shipping_country(),
            ];
        }

        return $addresses;
    }

    /**
     * Map contact employees.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return array<int, array<string, mixed>>
     */
    private function map_contacts(\WC_Order $order): array
    {
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();

        return [
            [
                'Name' => trim($first_name . ' ' . $last_name),
                'FirstName' => $first_name,
                'LastName' => $last_name,
                'E_Mail' => $order->get_billing_email(),
                'Phone1' => $order->get_billing_phone(),
                'MobilePhone' => $order->get_billing_phone(),
            ],
        ];
    }

    /**
     * Format address as single string.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @param string    $type  Address type.
     * @return string Formatted address.
     */
    private function format_address(\WC_Order $order, string $type): string
    {
        $method = 'get_' . $type . '_';

        $parts = array_filter([
            $order->{$method . 'address_1'}(),
            $order->{$method . 'address_2'}(),
        ]);

        return implode(', ', $parts);
    }
}
