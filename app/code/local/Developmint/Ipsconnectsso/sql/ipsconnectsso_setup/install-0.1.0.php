<?php

$installer = $this;
$installer->startSetup();
$installer->run("
    CREATE TABLE `{$installer->getTable('ipsconnectsso/ipsmagentocustomermap')}` (
      `map_id` int(11) NOT NULL auto_increment,
      `connect_id` int(11) NOT NULL,
      `customer_id` int(11) NOT NULL,
      PRIMARY KEY  (`map_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
");
$installer->endSetup();


