<?php

namespace Kodbruket\FixInventoryReservation\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

class CleanReservations
{
    const LOG_FILE = BP . '/var/log/clean_reservations_cron.log';
    /**
     * @var Resource
     */
    private $_resource;
    /**
     * @var Logger
     */
    private $_logger;
    /**
     * @var DateTime
     */
    private $_date;

    public function __construct(
        ResourceConnection $resource,
        Logger $logger,
        TimezoneInterface $date
    ) {
        $this->_resource = $resource;
        $this->_logger = $logger;
        $this->_date = $date;
    }

    public function execute()
    {
        $this->_logger->addWriter(new Stream(self::LOG_FILE));
        $this->_logger->info('Start cleanup inventory reservations ' . $this->_date->date()->format('m/d/Y h:i:s a'));

        /** @noinspection SqlDialectInspection */
        /** @noinspection SqlNoDataSourceInspection */
        $sql = strtr("
        DELETE res  
        FROM :sales_order_tbl o  
        JOIN :inventory_reservation_tbl res ON res.metadata like CONCAT('%\"object_type\":\"order\",\"object_id\":\"', o.entity_id, '\"}') 
        WHERE o.state IN ('canceled', 'closed', 'complete') AND o.created_at BETWEEN (NOW() - INTERVAL :interval) AND NOW()", [
            ':sales_order_tbl' => $this->_resource->getTableName('sales_order'),
            ':inventory_reservation_tbl' => $this->_resource->getTableName('inventory_reservation'),
            ':interval' => '30 DAY'
        ]);

        try {
            $result = $this->_resource->getConnection()->query($sql);
            $this->_logger->info('Total deleted reservations: ' . $result->rowCount());
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->_logger->err($e->getMessage(), $e);
        }

        return $this;
    }
}
