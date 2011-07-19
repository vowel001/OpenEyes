
<script language="javascript">

elements = new Array(
	'procedure_id',
	'ElementOperation_eye_0',
	'ElementOperation_eye_1',
	'ElementOperation_eye_2',
	'ElementOperation_total_duration',
	'decision_date_day',
	'decision_date_month',
	'decision_date_year',
	'ElementOperation_consultant_required_0',
	'ElementOperation_consultant_required_1',
	'ElementOperation_anaesthetic_type_0',
	'ElementOperation_anaesthetic_type_1',
	'ElementOperation_anaesthetic_type_2',
	'ElementOperation_anaesthetic_type_3',
	'ElementOperation_anaesthetic_type_4',
	'ElementOperation_overnight_stay_0',
	'ElementOperation_overnight_stay_1',
	'ElementOperation_comments',
	'schedule_timeframe1_0',
	'schedule_timeframe1_1',
	'schedule_timeframe2'
);

$(document).ready(function() {
	if (!$('#ElementDiagnosis_disorder_id').val() && !$('ElementDiagnosis_eye').val()) {
		disableElements();
	}
});

$(function() {
	$('#ElementDiagnosis_disorder_id').change(function() {
		checkDisable();
	});
});
$(function() {
	$('#ElementDiagnosis_eye_0').change(function() {
		checkDisable();

		if ($('#ElementDiagnosis_eye_0').attr('checked')) {
			$('#ElementOperation_eye_0').attr("checked", true);
			$('#ElementOperation_eye_1').attr("disabled", "disabled");
			$('#ElementOperation_eye_2').attr("disabled", "disabled");
		}
	});
});
$(function() {
	$('#ElementDiagnosis_eye_1').change(function() {
		checkDisable();

		if ($('#ElementDiagnosis_eye_1').attr('checked')) {
			$('#ElementOperation_eye_1').attr("checked", true);
			$('#ElementOperation_eye_0').attr("disabled", "disabled");
			$('#ElementOperation_eye_2').attr("disabled", "disabled");
		}
	});
});
$(function() {
	$('#ElementDiagnosis_eye_2').change(function() {
		checkDisable();
	});
});

function checkDisable() {
	if ($('#ElementDiagnosis_disorder_id').val() && (
		$('#ElementDiagnosis_eye_0').attr('checked') ||
		$('#ElementDiagnosis_eye_1').attr('checked') ||
		$('#ElementDiagnosis_eye_2').attr('checked')
	)) {
		enableElements();
	} else {
		disableElements();
	}
}

function disableElements() {
	for (i in elements) {
		$('#' + elements[i]).attr("disabled", "disabled");
	}

	$('input[name=yt1]').attr('disabled', 'disabled');
}

function enableElements() {
	for (i in elements) {
		$('#' + elements[i]).removeAttr("disabled");
	}

	// This decides whether to disable an element in the 'element_operation' view
	var select = $('input[name=schedule_timeframe1]:checked').val();
			
	if (select == 1) {
		$('select[name=schedule_timeframe2]').attr('disabled', false);
	} else {
		$('select[name=schedule_timeframe2]').attr('disabled', true);
	}

	$('input[name=yt1]').removeAttr('disabled');
}

</script>

<div class="row">
	<label for="ElementOperation_value">Disorder:</label>

<?php

if (empty($model->event_id)) {
	// It's a new event so fetch the most recent element_diagnosis
	$diagnosis = $model->getNewestDiagnosis();

	if (empty($diagnosis->disorder)) {
		// There is no diagnosis for this episode, or no episode, or the diagnosis has no disorder (?)
		$value = '';
		$diagnosis = $model;
	} else {
		// There is a diagnosis for this episode
		$value = $diagnosis->disorder->term . ' - ' . $diagnosis->disorder->fully_specified_name;
	}
} else {
	$value = $model->disorder->term . ' - ' . $model->disorder->fully_specified_name;
	$diagnosis = $model;
}

$this->widget('zii.widgets.jui.CJuiAutoComplete', array(
    'name'=>'ElementDiagnosis[disorder_id]',
    'value'=>$value,
    'sourceUrl'=>array('disorder/autocomplete'),
    'htmlOptions'=>array(
	'style'=>'height:20px;width:200px;'
    ),
));
?>
</div>

<div class="row">
<label for="ElementDiagnosis_eye">Eye(s) diagnosed:</label>
<?php echo CHtml::activeRadioButtonList($diagnosis, 'eye', $model->getEyeOptions(),
	array('separator' => ' &nbsp; ')); ?>
</div>
<br />
