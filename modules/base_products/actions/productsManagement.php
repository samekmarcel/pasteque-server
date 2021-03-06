<?php
//    Pastèque Web back office, Users module
//
//    Copyright (C) 2013-2016 Scil (http://scil.coop)
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
namespace BaseProducts;

// open csv return null if the file selected had not extension "csv"
// or user not selected file
function init_csv() {
	if ($_FILES['csv']['tmp_name'] === NULL) {
		return NULL;
	}
	$ext = strchr($_FILES['csv']['type'], "/");
	$ext = strtolower($ext);

	if($ext !== "/csv" && $ext !== "/plain") {
		return NULL;
	}

	$key = array('reference', 'barcode', 'label', 'sellVat',
			'category', 'tax_cat');

	$optionKey = array('price_buy', 'visible', 'scaled', 'disp_order',
			'discount_rate', 'discount_enabled', 'stock_min',
			'provider', 'stock_max');

	$csv = new \Pasteque\Csv($_FILES['csv']['tmp_name'], $key, $optionKey,
			PLUGIN_NAME);
	if (!$csv->open()) {
		return $csv;
	}

	//manage empty string
	$csv->setEmptyStringValue("visible", true);
	$csv->setEmptyStringValue("scaled", false);
	$csv->setEmptyStringValue("disp_order", null);
	$csv->setEmptyStringValue("provider", null);
	return $csv;
}

// return an array whith all key set
function initArray($key, $tab) {
	$array = array_fill_keys($key, NULL);
	$array['visible'] = true;
	$array['scaled'] = false;

	foreach ($tab as $field => $value) {
		$array[$field] = $value;
	}
	return $array;
}

function import_csv($csv) {
	$error = 0;
	$create = 0;
	$update = 0;
	$error_mess = array();

	while ($tab = $csv->readLine()) {
		//init optionnal values
		$AllKeyPossible = array_merge($csv->getKeys(), $csv->getOptionalKeys());
		$tab = initArray($AllKeyPossible, $tab);

		//check
		$category = \Pasteque\CategoriesService::getByName($tab['category']);
		$taxCat = \Pasteque\TaxesService::getByName($tab['tax_cat']);

		if ($taxCat && $category) {
			$prod = readProductLine($tab, $category, $taxCat);
			$product_exist = \Pasteque\ProductsService::getByRef($prod->reference);
			if ($product_exist !== null ) {
				// update product
				$prod->id = $product_exist->id;
				$prod = mergeProduct($product_exist, $prod);
				//if update imposible an is occurred
				if (!\Pasteque\ProductsService::update($prod)) {
					$error++;
					$error_mess[] = \i18n("On line %d: "
							. "Cannot update product: '%s'", PLUGIN_NAME,
							$csv->getCurrentLineNumber(), $tab['label']);
				} else {
					// update stock_curr and stock_diary
					manage_stock_level($prod->id, $tab, FALSE);
					$update++;
				}

			} else {
				// create product
				$id = \Pasteque\ProductsService::create($prod);
				if ($id) {
					//create stock_curr and stock diary
					manage_stock_level($id, $tab, TRUE);
					$create++;
				} else {
					$error++;
					$error_mess[] = \i18n("On line %d: "
							. "Cannot create product: '%s'", PLUGIN_NAME,
							$csv->getCurrentLineNumber(), $tab['label']);
				}
			}
		} else {
			// Missing category or tax category
			$error++;
			if (!$category) {
				$error_mess[] = \i18n("On line %d "
						. "category: '%s' doesn't exist", PLUGIN_NAME,
						$csv->getCurrentLineNumber(), $tab['category']);
			}
			if (!$taxCat) {
				$error_mess[] = \i18n("On line %d: "
						. "Tax category: '%s' doesn't exist", PLUGIN_NAME,
						$csv->getCurrentLineNumber(), $tab['tax_cat']);
			}
		}
	}

	$message = \i18n("%d line(s) inserted, %d line(s) modified, %d error(s)",
			PLUGIN_NAME, $create, $update, $error);
	return array($message, $error_mess);
}

// add to product values not obligatory may be present in array
function readProductLine($line, $category, $taxCat) {
	$priceSell =  $line['sellVat'] / ( 1 + $taxCat->getCurrentTax()->rate);
	if (isset($line['visible'])) {
		$visible = $line['visible'];
	} else {
		$visible = true;
	}
	if (isset($line['scaled']) && ($line['scaled'] !== 1 || $line['scaled'] !== true)) {
		$scaled = $line['scaled'];
	} else {
		$scaled = false;
	}
	if (isset($line['provider'])) {
		$provider = \Pasteque\ProvidersService::getByName($line['provider']);
	}
	if (isset($line['disp_order'])) {
		$dispOrder = $line['disp_order'];
	} else {
		$dispOrder = null;
	}
	$product = new \Pasteque\Product($line['reference'], $line['label'],
			$priceSell, $category->id, $provider->id, $dispOrder,
			$taxCat->id, $visible, $scaled);
	if (isset($line['barcode'])) {
		$product->barcode = $line['barcode'];
	}
	if (isset($line['price_buy'])) {
		$product->priceBuy = $line['price_buy'];
	}
	if (isset($line['discount_enabled'])) {
		$product->discountEnabled = $line['discount_enabled'];
	}
	if (isset($line['discount_rate'])) {
		$product->discountRate = $line['discount_rate'];
	}
	// TODO: add support for attribute sets
	return $product;
}

/** Manage stockDiary and stockCurr whith id and location by default:"Principal"
 * check if fields 'stock_min' and 'stock_max' are set in array
 * if $create is true create a new entry in stockDiary and stockCurr in BDD
 * else update stockDiarry and  stockCurr.
 */
function manage_stock_level($id, $array) {
	$level = \Pasteque\StocksService::getLevel($id, "0", null);
	$min = null;
	$max = null;
	if (isset($array['stock_min'])) {
		$min = $array['stock_min'];
	}
	if (isset($array['stock_max'])) {
		$max = $array['stock_max'];
	}
	if ($level !== null) {
		// Update existing level
		if ($min !== null) {
			$level->security = $min;
		}
		if ($max !== null) {
			$level->max = $max;
		}
		return \Pasteque\StocksService::updateLevel($level);
	} else {
		// Create a new level
		$level = new \Pasteque\StockLevel($id, "0", null, $min, $max);
		return \Pasteque\StocksService::createLevel($level);
	}
}

/* merge the old field values of product to new product
 * if the fields corresponding are not set */
function mergeProduct($old, $new) {
	if (!isset($new->barcode)) {
		$new->barcode = $old->barcode;
	}
	if (!isset($new->providerId)) {
		$new->providerId = $old->providerId;
	}
	if (!isset($new->price_buy)) {
		$new->priceBuy = $old->priceBuy;
	}
	if (!isset($new->hasImage)) {
		$new->hasImage = $old->hasImage;
	}
	if (!isset($new->discountEnabled)) {
		$new->discountEnabled = $old->discountEnabled;
	}
	if (!isset($new->discountRate)) {
		$new->discountRate = $old->discountRate;
	}
	if (!isset($new->attributeSetId)) {
		$new->attributeSetId = $old->attributeSetId;
	}
	return $new;
}

$error = null;
$message = null;
if (isset($_FILES['csv'])) {
	$csv = init_csv();
	if ($csv === NULL) {
		$error = \i18n("Selected file empty or bad format", PLUGIN_NAME);
	} else if (!$csv->isOpen()) {
		$err = array();
		foreach ($csv->getErrors() as $mess) {
			$err[] = \i18n($mess);
		}
		if (count($err) > 0) {
			$error = $err;
		}
	} else {
		$msgs = import_csv($csv);
		$message = $msgs[0];
		$error = $msgs[1];
	}
}

echo \Pasteque\mainTitle(\i18n("Import products from csv file", PLUGIN_NAME));
\Pasteque\tpl_msg_box($message, $error);
$content = \Pasteque\form_file("csv","csv",\i18n("File",PLUGIN_NAME));
$content .= \Pasteque\form_send();
echo \Pasteque\form_generate(\Pasteque\get_current_url(), "post", $content);
?>
