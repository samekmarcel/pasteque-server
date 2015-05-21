<?php
//    Pastèque Web back office, Customers module
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

namespace BaseCustomers;

$srv = new \Pasteque\CustomersService();
if (isset($_GET['delete-customer'])) {
    $srv->delete($_GET['delete-customer']);
}

$customers = $srv->getAll(true);
?>

<!-- start bloc titre -->
<div class="blc_ti">

<h1><?php \pi18n("Customers", PLUGIN_NAME); ?></h1>

<ul class="bt_fonction">
	<li><a class="bt_add transition" href="<?php echo \Pasteque\get_module_url_action(PLUGIN_NAME, 'customer_edit'); ?>"><?php \pi18n("Add a customer", PLUGIN_NAME); ?></a></li>
</ul>

<div><span class="nb_article"><?php \pi18n("%d customers", PLUGIN_NAME, count($customers)); ?></span></div>

</div>
<!-- end bloc titre -->

<!-- start container scroll -->
<div class="container_scroll">
            
            	<div class="stick_row stickem-container">
                    
                    <!-- start colonne contenu -->
                    <div id="content_liste" class="grid_9">
                    
                        <div class="blc_content">



<table cellspacing="0" cellpadding="0">
	<thead>
		<tr>
			<th><?php \pi18n("Customer.number"); ?></th>
			<th><?php \pi18n("Customer.key"); ?></th>
			<th><?php \pi18n("Customer.dispName"); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
<?php
foreach ($customers as $cust) {
?>
	<tr>
		<td><?php echo $cust->number; ?></td>
		<td><?php echo $cust->key; ?></td>
		<td><?php echo $cust->dispName; ?></td>
		<td class="edition">
                    <?php \Pasteque\tpl_btn('btn-edition', \Pasteque\get_module_url_action(
                            PLUGIN_NAME, 'customer_edit', array("id" => $cust->id)), "",
                            'img/edit.png', \i18n('Edit'), \i18n('Edit'));
                    ?>
                    <?php \Pasteque\tpl_btn('btn-delete', \Pasteque\get_current_url() . "&delete-customer=" . $cust->id, "",
                            'img/delete.png', \i18n('Delete'), \i18n('Delete'), true);
                    ?>
		</td>
	</tr>
<?php
}
?>
	</tbody>
</table>
</div></div>
                    <!-- end colonne contenu -->
                    
                    <!-- start sidebar menu -->
                    <div id="sidebar_menu" class="grid_3 stickem">
                    
                        <div class="blc_content">
                            
                            <!-- start texte editorial -->
                            <div class="edito"><!-- zone_edito --></div>
                            <!-- end texte editorial -->
                            
                            
                        </div>
                        
                    </div>
                    <!-- end sidebar menu -->
                    
        		</div>
                
        	</div>
            <!-- end container scroll -->
