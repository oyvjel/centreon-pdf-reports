--
-- Add topology
--
-- OJ: TODO: topology_icone has gone! 
INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`, 
       `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`) 
   VALUES ('PDF Reports', 501, 50120, 150, 1, './modules/pdfreports/pdfreportsOpt.php', '&o=pdfreports', '0', '1', '1', NULL, NULL, NULL);
INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`,
       `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`)
   VALUES ('PDF Reports', 6, 640, 100, 1, './modules/pdfreports/report.php', NULL, '0', '1', '1', NULL, NULL, NULL);
INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`,
        `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`)
   VALUES ('PDF Reports', 640, NULL, NULL, 1, NULL, NULL, NULL, NULL, '1', NULL, NULL, NULL);
INSERT INTO `topology` (`topology_name`, `topology_parent`, `topology_page`, `topology_order`, `topology_group`,
        `topology_url`, `topology_url_opt`, `topology_popup`, `topology_modules`, `topology_show`, `topology_style_class`, `topology_style_id`, `topology_OnClick`)
   VALUES ('Reports', 640, 64001, 10, 1, './modules/pdfreports/report.php', NULL, '0', '1', '1', NULL, NULL, NULL);



--
-- Structure de la table `pdfreports_reports`
--

CREATE TABLE IF NOT EXISTS `pdfreports_reports` (
  `report_id` int(11) NOT NULL auto_increment,
  `name` varchar(254) default NULL,
  `report_description` varchar(254) default NULL,
  `period` varchar(254) default NULL,
  `bcsv` boolean default NULL,
  `btimeline` boolean default NULL,
  `bsummary_only` boolean default NULL,
  `bdebug` boolean default NULL,
  `report_title` varchar(254) default NULL, 
  `report_template` varchar(254) default NULL, 
  `subject` varchar(254) default NULL,
  `mail_body` varchar(254) default NULL,
  `service_category` varchar(60) default NULL,
  `retention` int(11) default NULL,
  `report_comment` text,
  `activate` enum('0','1') default NULL,
  PRIMARY KEY  (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- alter table  pdfreports_reports add column `bcsv` boolean default NULL;
-- alter table  pdfreports_reports add column `btimeline` boolean default NULL;
-- alter table  pdfreports_reports add column `bsummary_only` boolean default NULL;
-- alter table  pdfreports_reports add column `bdebug` boolean default NULL;

-- alter table  pdfreports_reports add column `report_template` varchar(254) default NULL;

-- alter table  pdfreports_reports add column `service_category` int(11) default NULL;
-- or: alter table  pdfreports_reports change service_category service_category varchar(60);
-- --------------------------------------------------------

--
-- Structure de la table `pdfreports_reports_contactgroup_relation`
--

CREATE TABLE IF NOT EXISTS `pdfreports_reports_contactgroup_relation` (
  `rcgr_id` int(11) NOT NULL auto_increment,
  `reports_rp_id` int(11) default NULL,
  `contactgroup_cg_id` int(11) default NULL,
  PRIMARY KEY  (`rcgr_id`),
  KEY `reports_index` (`reports_rp_id`),
  KEY `cg_index` (`contactgroup_cg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Structure de la table `pdfreports_reports_contact_relation`
--

CREATE TABLE IF NOT EXISTS `pdfreports_reports_contact_relation` (
  `rcr_id` int(11) NOT NULL auto_increment,
  `reports_rp_id` int(11) default NULL,
  `contact_c_id` int(11) default NULL,
  PRIMARY KEY  (`rcr_id`),
  KEY `reports_index` (`reports_rp_id`),
  KEY `cg_index` (`contact_c_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Structure de la table `pdfreports_reports_servicegroup_relation`
--

CREATE TABLE IF NOT EXISTS `pdfreports_reports_servicegroup_relation` (
  `rsgr_id` int(11) NOT NULL auto_increment,
  `reports_rp_id` int(11) default NULL,
  `servicegroup_sg_id` int(11) default NULL,
  PRIMARY KEY  (`rsgr_id`),
  KEY `reports_index` (`reports_rp_id`),
  KEY `sg_index` (`servicegroup_sg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Structure de la table `pdfreports_host_report_relation`
--

CREATE TABLE IF NOT EXISTS `pdfreports_host_report_relation` (
  `hrr_id` int(11) NOT NULL auto_increment,
  `hostgroup_hg_id` int(11) default NULL,
  `host_host_id` int(11) default NULL,
  `reports_rp_id` int(11) default NULL,
  PRIMARY KEY  (`hrr_id`),
  KEY `hostgroup_index` (`hostgroup_hg_id`),
  KEY `host_index` (`host_host_id`),
  KEY `reports_rp_id` (`reports_rp_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

