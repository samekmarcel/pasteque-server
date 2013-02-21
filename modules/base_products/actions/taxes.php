<?php
//    Pastèque Web back office, Products module
//
//    Copyright (C) 2013 Scil (http://scil.coop)
//
//    This file is part of Pastèque.
//
//    Pastèque is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    Pastèque is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with Pastèque.  If not, see <http://www.gnu.org/licenses/>.

// List all tax categories

namespace BaseProducts;

if (isset($_POST['delete-taxcat'])) {
    \Pasteque\TaxesService::deleteCat($_POST['delete-taxcat']);
}

$taxes = \Pasteque\TaxesService::getAll();
?>
<h1><?php \pi18n("Taxes", PLUGIN_NAME); ?></h1>

<p><a href="<?php echo \Pasteque\get_module_url_action(PLUGIN_NAME, 'tax_edit'); ?>" class="btn btn-primary"><?php \pi18n("Add a tax", PLUGIN_NAME); ?></a></p>

<table cellpadding="0" cellspacing="0">
	<thead>
		<tr>
			<th><?php \pi18n("TaxCat.label"); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
<?php
foreach ($taxes as $tax) {
?>
	<tr>
		<td><?php echo $tax->label; ?></td>
		<td class="edition">
			<a href="<?php echo \Pasteque\get_module_url_action(PLUGIN_NAME, 'tax_edit', array('id' => $tax->id)); ?>"><img src="<?php echo \Pasteque\get_template_url(); ?>img/edit.png" alt="<?php \pi18n('Edit'); ?>" title="<?php \pi18n('Edit'); ?>"></a>
			<form action="<?php echo \Pasteque\get_current_url(); ?>" method="post"><?php \Pasteque\form_delete("taxcat", $tax->id, \Pasteque\get_template_url() . 'img/delete.png') ?></form>
		</td>
	</tr>
<?php
}
?>
	</tbody>
</table>
<?php
if (count($tax) == 0) {
?>
<div class="alert"><?php \pi18n("No tax found", PLUGIN_NAME); ?></div>
<?php
}
?>
