<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


$triggersWidget = new CWidget(null, 'trigger-list');

// append host summary to widget header
if (!empty($this->data['hostid'])) {
	if (!empty($this->data['parent_discoveryid'])) {
		$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid'], $this->data['parent_discoveryid']));
	}
	else {
		$triggersWidget->addItem(get_header_host_table('triggers', $this->data['hostid']));
	}
}

// create new application button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addVar('hostid', $this->data['hostid']);

if (!empty($this->data['parent_discoveryid'])) {
	$createForm->addItem(new CSubmit('form', _('Create trigger prototype')));
	$createForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGER PROTOTYPES'), $createForm);
}
else {
	if (empty($this->data['hostid'])) {
		$createButton = new CSubmit('form', _('Create trigger (select host first)'));
		$createButton->setEnabled(false);
		$createForm->addItem($createButton);
	}
	else {
		$createForm->addItem(new CSubmit('form', _('Create trigger')));
	}

	$triggersWidget->addPageHeader(_('CONFIGURATION OF TRIGGERS'), $createForm);
}

// create widget header
if (!empty($this->data['parent_discoveryid'])) {
	$triggersWidget->addHeader(array(_('Trigger prototypes of').SPACE, new CSpan($this->data['discovery_rule']['name'], 'parent-discovery')));
	$triggersWidget->addHeaderRowNumber(array(
		'[ ',
		new CLink(
			$this->data['showdisabled'] ? _('Hide disabled trigger prototypes') : _('Show disabled trigger prototypes'),
			'trigger_prototypes.php?'.
				'showdisabled='.($this->data['showdisabled'] ? 0 : 1).
				'&hostid='.$this->data['hostid'].
				'&parent_discoveryid='.$this->data['parent_discoveryid']
		),
		' ]'
	));
}
else {
	$filterForm = new CForm('get');
	$filterForm->addItem(array(_('Group').SPACE, $this->data['pageFilter']->getGroupsCB()));
	$filterForm->addItem(array(SPACE._('Host').SPACE, $this->data['pageFilter']->getHostsCB()));

	$triggersWidget->addHeader(_('Triggers'), $filterForm);
	$triggersWidget->addHeaderRowNumber(array(
		'[ ',
		new CLink(
			$this->data['showdisabled'] ? _('Hide disabled triggers') : _('Show disabled triggers'),
			'triggers.php?'.
				'hostid='.$this->data['hostid'].
				'&showdisabled='.($this->data['showdisabled'] ? 0 : 1)
		),
		' ]'
	));
}

// create form
$triggersForm = new CForm();
$triggersForm->setName('triggersForm');
$triggersForm->addVar('parent_discoveryid', $this->data['parent_discoveryid']);
$triggersForm->addVar('hostid', $this->data['hostid']);

// create table
$triggersTable = new CTableInfo(_('No triggers found.'));
$triggersTable->setHeader(array(
	new CCheckBox('all_triggers', null, "checkAll('".$triggersForm->getName()."', 'all_triggers', 'g_triggerid');"),
	make_sorting_header(_('Severity'), 'priority', $this->data['sort'], $this->data['sortorder']),
	empty($this->data['hostid']) ? _('Host') : null,
	make_sorting_header(_('Name'), 'description', $this->data['sort'], $this->data['sortorder']),
	_('Expression'),
	make_sorting_header(_('Status'), 'status', $this->data['sort'], $this->data['sortorder']),
	$data['showInfoColumn'] ? _('Info') : null
));

foreach ($this->data['triggers'] as $tnum => $trigger) {
	$triggerid = $trigger['triggerid'];
	$trigger['discoveryRuleid'] = $this->data['parent_discoveryid'];

	// description
	$description = array();

	$trigger['hosts'] = zbx_toHash($trigger['hosts'], 'hostid');
	$trigger['items'] = zbx_toHash($trigger['items'], 'itemid');
	$trigger['functions'] = zbx_toHash($trigger['functions'], 'functionid');

	if ($trigger['templateid'] > 0) {
		if (!isset($this->data['realHosts'][$triggerid])) {
			$description[] = new CSpan(empty($this->data['parent_discoveryid']) ? _('Host') : _('Template'), 'unknown');
			$description[] = NAME_DELIMITER;
		}
		else {
			$real_hosts = $this->data['realHosts'][$triggerid];
			$real_host = reset($real_hosts);

			if (!empty($this->data['parent_discoveryid'])) {
				$tpl_disc_ruleid = get_realrule_by_itemid_and_hostid($this->data['parent_discoveryid'], $real_host['hostid']);
				$description[] = new CLink(
					CHtml::encode($real_host['name']),
					'trigger_prototypes.php?hostid='.$real_host['hostid'].'&parent_discoveryid='.$tpl_disc_ruleid,
					'unknown'
				);
			}
			else {
				$description[] = new CLink(
					CHtml::encode($real_host['name']),
					'triggers.php?hostid='.$real_host['hostid'],
					'unknown'
				);
			}
			$description[] = NAME_DELIMITER;
		}
	}

	if (empty($this->data['parent_discoveryid'])) {
		if (!empty($trigger['discoveryRule'])) {
			$description[] = new CLink(
				CHtml::encode($trigger['discoveryRule']['name']),
				'trigger_prototypes.php?'.
					'hostid='.$this->data['hostid'].'&parent_discoveryid='.$trigger['discoveryRule']['itemid'],
				'parent-discovery'
			);
			$description[] = NAME_DELIMITER.$trigger['description'];
		}
		else {
			$description[] = new CLink(
				CHtml::encode($trigger['description']),
				'triggers.php?form=update&hostid='.$this->data['hostid'].'&triggerid='.$triggerid
			);
		}

		$dependencies = $trigger['dependencies'];
		if (count($dependencies) > 0) {
			$description[] = array(BR(), bold(_('Depends on').NAME_DELIMITER));
			$triggerDependencies = array();

			foreach ($dependencies as $dependency) {
				$depTrigger = $this->data['dependencyTriggers'][$dependency['triggerid']];
				$hostNames = array();

				foreach ($depTrigger['hosts'] as $host) {
					$hostNames[] = CHtml::encode($host['name']);
					$hostNames[] = ', ';
				}
				array_pop($hostNames);

				if ($depTrigger['flags'] == ZBX_FLAG_DISCOVERY_NORMAL) {
					$host = reset($depTrigger['hosts']);
					$triggerDependencies[] = new CLink(
						array($hostNames, NAME_DELIMITER, CHtml::encode($depTrigger['description'])),
						'triggers.php?form=update&hostid='.$host['hostid'].'&triggerid='.$depTrigger['triggerid'],
						triggerIndicatorStyle($depTrigger['status'])
					);
				}
				else {
					$triggerDependencies[] = array($hostNames, NAME_DELIMITER, $depTrigger['description']);
				}

				$triggerDependencies[] = BR();
			}
			array_pop($triggerDependencies);

			$description = array_merge($description, array(new CDiv($triggerDependencies, 'dependencies')));
		}
	}
	else {
		$description[] = new CLink(
			CHtml::encode($trigger['description']),
			'trigger_prototypes.php?'.
				'form=update'.
				'&hostid='.$this->data['hostid'].
				'&parent_discoveryid='.$this->data['parent_discoveryid'].
				'&triggerid='.$triggerid
		);
	}

	// info
	if ($data['showInfoColumn']) {
		if ($trigger['status'] == TRIGGER_STATUS_ENABLED && !zbx_empty($trigger['error'])) {
			$info = new CDiv(SPACE, 'status_icon iconerror');
			$info->setHint($trigger['error'], 'on');
		}
		else {
			$info = '';
		}
	}
	else {
		$info = null;
	}

	// status
	$status = '';
	if (!empty($this->data['parent_discoveryid'])) {
		$status = new CLink(
			triggerIndicator($trigger['status']),
			'trigger_prototypes.php?'.
				'action='.($trigger['status'] == TRIGGER_STATUS_DISABLED
					? 'triggerprototype.massenable'
					: 'triggerprototype.massdisable'
				).
				'&hostid='.$this->data['hostid'].
				'&g_triggerid='.$triggerid.
				'&parent_discoveryid='.$this->data['parent_discoveryid'],
			triggerIndicatorStyle($trigger['status'])
		);
	}
	else {
		$status = new CLink(
			triggerIndicator($trigger['status'], $trigger['state']),
			'triggers.php?'.
				'action='.($trigger['status'] == TRIGGER_STATUS_DISABLED
					? 'trigger.massenable'
					: 'trigger.massdisable'
				).
				'&hostid='.$this->data['hostid'].
				'&g_triggerid='.$triggerid,
			triggerIndicatorStyle($trigger['status'], $trigger['state'])
		);
	}

	// hosts
	$hosts = null;
	if (empty($this->data['hostid'])) {
		foreach ($trigger['hosts'] as $hostid => $host) {
			if (!empty($hosts)) {
				$hosts[] = ', ';
			}
			$hosts[] = $host['name'];
		}
	}

	// checkbox
	$checkBox = new CCheckBox('g_triggerid['.$triggerid.']', null, null, $triggerid);
	$checkBox->setEnabled(empty($trigger['discoveryRule']));

	$triggersTable->addRow(array(
		$checkBox,
		getSeverityCell($trigger['priority']),
		$hosts,
		$description,
		new CCol(triggerExpression($trigger, true), 'trigger-expression'),
		$status,
		$info
	));
}

// create go button

$actionObject = $this->data['parent_discoveryid'] ? 'triggerprototype' : 'trigger';

$goComboBox = new CComboBox('action');

$goOption = new CComboItem($actionObject.'.massenable', _('Enable selected'));
$goOption->setAttribute(
	'confirm',
	$this->data['parent_discoveryid'] ? _('Enable selected trigger prototypes?') : _('Enable selected triggers?')
);
$goComboBox->addItem($goOption);

$goOption = new CComboItem($actionObject.'.massdisable', _('Disable selected'));
$goOption->setAttribute(
	'confirm',
	$this->data['parent_discoveryid'] ? _('Disable selected trigger prototypes?') : _('Disable selected triggers?')
);
$goComboBox->addItem($goOption);

$goOption = new CComboItem($actionObject.'.massupdateform', _('Mass update'));
$goComboBox->addItem($goOption);

if (empty($this->data['parent_discoveryid'])) {
	$goOption = new CComboItem($actionObject.'.masscopyto', _('Copy selected to ...'));
	$goComboBox->addItem($goOption);
}

$goOption = new CComboItem($actionObject.'.massdelete', _('Delete selected'));
$goOption->setAttribute(
	'confirm',
	$this->data['parent_discoveryid'] ? _('Delete selected trigger prototypes?') : _('Delete selected triggers?')
);
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');

zbx_add_post_js('chkbxRange.pageGoName = "g_triggerid";');
if (empty($this->data['parent_discoveryid'])) {
	zbx_add_post_js('chkbxRange.prefix = "'.$this->data['hostid'].'";');
	zbx_add_post_js('cookie.prefix = "'.$this->data['hostid'].'";');
}
else {
	zbx_add_post_js('chkbxRange.prefix = "'.$this->data['parent_discoveryid'].'";');
	zbx_add_post_js('cookie.prefix = "'.$this->data['parent_discoveryid'].'";');
}

// append table to form
$triggersForm->addItem(array($this->data['paging'], $triggersTable, $this->data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$triggersWidget->addItem($triggersForm);

return $triggersWidget;
