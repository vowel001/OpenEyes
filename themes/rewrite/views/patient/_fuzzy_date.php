<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */
?>
							<fieldset class="row field-row">
								<legend class="large-3 column">
									Date:
								</legend>
								<div class="large-2 column">
									<select>
									<option value="">Day (optional)</option>
									<?php for ($i=1;$i<=31;$i++) {?>
										<option value="<?php echo $i?>"><?php echo $i?></option>
									<?php }?>
									</select>
								</div>
								<div class="large-2 column">
									<select>
										<option>Month (optional)</option>
									<?php foreach (array('January','February','March','April','May','June','July','August','September','October','November','December') as $i => $month) {?>
										<option value="<?php echo $i+1?>"><?php echo $month?></option>
									<?php }?>
									</select>
								</div>
								<div class="large-2 column end">
									<select>
									<?php for ($i=date('Y')-50;$i<=date('Y');$i++) {?>
										<option value="<?php echo $i?>"<?php if (date('Y') == $i) {?> selected="selected"<?php }?>><?php echo $i?></option>
									<?php }?>
									</select>
								</div>
							</fieldset>
