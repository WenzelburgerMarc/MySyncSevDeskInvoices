<?php

class DatabaseOperations extends Module {

    // Execute SQL Query
    private function executeSqlQuery($sql): bool
    {
        return Db::getInstance()->execute($sql);
    }

    // Install sevDesk invoices table in DB
    public function installExistingSevDeskInvoicesSQL(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sevdesk_invoices` (
        `id_sevdesk_invoice` INT(11) NOT NULL AUTO_INCREMENT,
        `id_order` INT(11) NOT NULL,
        `id_sevdesk` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_sevdesk_invoice`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return $this->executeSqlQuery($sql);
    }

    // Uninstall sevDesk invoices table from DB
    public function uninstallExistingSevDeskInvoicesSQL(): bool
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sevdesk_invoices`;';
        return $this->executeSqlQuery($sql);
    }

    // Install log table in DB
    public function installLogSQL(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sevdesk_logs` (
        `id_log` INT(11) NOT NULL AUTO_INCREMENT,
        `log` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_log`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return $this->executeSqlQuery($sql);
    }

    // Uninstall log table from DB
    public function uninstallLogSQL(): bool
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'sevdesk_logs`;';
        return $this->executeSqlQuery($sql);
    }

    // Get Existing Sev Desk Invoice ID By PS Order ID
    public function getSevDeskInvoiceIdByPsOrderId($order_id)
    {
        $sql = 'SELECT id_sevdesk FROM `' . _DB_PREFIX_ . 'sevdesk_invoices` WHERE id_order = ' . $order_id;
        $result = Db::getInstance()->executeS($sql);
        return $result[0]['id_sevdesk'];
    }

    // Get total paid amount from order id in prestashop
    public function getTotalPaidAmountFromOrderId($order_id)
    {
        $sql = 'SELECT total_paid_tax_incl FROM `' . _DB_PREFIX_ . 'orders` WHERE id_order = ' . $order_id;
        $result = Db::getInstance()->executeS($sql);
        return $result[0]['total_paid_tax_incl'];
    }

    // Add new sevDesk invoice in DB
    public function addExistingSevDeskInvoiceToDb($id_prestashop_order, $id_sevdesk_invoice, $created_at = null, $updated_at = null): void
    {
        $columns = '`id_order`, `id_sevdesk`';
        $values = '\'' . pSQL($id_prestashop_order) . '\', \'' . pSQL($id_sevdesk_invoice) . '\'';

        if ($created_at !== null) {
            $columns .= ', `created_at`';
            $values .= ', \'' . pSQL($created_at) . '\'';
        }

        if ($updated_at !== null) {
            $columns .= ', `updated_at`';
            $values .= ', \'' . pSQL($updated_at) . '\'';
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'sevdesk_invoices` (' . $columns . ') VALUES (' . $values . ');';
        $this->executeSqlQuery($sql);
    }

    // Add new log in DB
    public function addLog($text, $created_at = null, $updated_at = null)
    {
        $text = $this->l($text);

        $columns = '`log`';
        $values = '\'' . pSQL($text) . '\'';

        if ($created_at !== null) {
            $columns .= ', `created_at`';
            $values .= ', \'' . pSQL($created_at) . '\'';
        }

        if ($updated_at !== null) {
            $columns .= ', `updated_at`';
            $values .= ', \'' . pSQL($updated_at) . '\'';
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'sevdesk_logs` (' . $columns . ') VALUES (' . $values . ');';
        return $this->executeSqlQuery($sql);
    }
}