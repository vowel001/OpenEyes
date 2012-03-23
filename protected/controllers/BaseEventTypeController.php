<?php

class BaseEventTypeController extends BaseController
{
	public $model;
	public $firm;
	public $patient;
	public $editable;
	public $editing;
	public $event;
	public $event_type;
	public $title;
	public $cssPath;
	public $jsPath;
	public $imgPath;

	public function actionIndex()
	{
		$this->render('index');
	}

	protected function beforeAction($action)
	{
		parent::storeData();

		$this->firm = Firm::model()->findByPk($this->selectedFirmId);

		if (!isset($this->firm)) {
			// No firm selected, reject
			throw new CHttpException(403, 'You are not authorised to view this page without selecting a firm.');
		}

		return parent::beforeAction($action);
	}

	/**
	 * Get all the elements for an event, the current module or an event_type
	 *
	 * @return array
	 */
	public function getDefaultElements($event_type_id=false, $event=false) {
		if (!$event && isset($this->event)) {
			$event = $this->event;
		}

		if (isset($event->event_type_id)) {
			$event_type = EventType::model()->find('id = ?',array($event->event_type_id));
		} else if ($event_type_id) {
			$event_type = EventType::model()->find('id = ?',array($event_type_id));
		} else {
			$event_type = EventType::model()->find('class_name = ?',array($this->getModule()->name));
		}

		if ($event_type->class_name != 'OphTrOperation') {
			// Import the element_type models
			foreach (ElementType::model()->findAll('event_type_id = ?',array($event_type->id)) as $element_type) {
				Yii::import('application.modules.'.$event_type->class_name.'.models.'.$element_type->class_name);
			}
		}

		$criteria = new CDbCriteria;
		$criteria->compare('event_type_id',$event_type->id);
		$criteria->order = 'display_order asc';

		$elements = array();

		if (isset($event->event_type_id)) {
			foreach (ElementType::model()->findAll($criteria) as $element_type) {
				$element_class = $element_type->class_name;

				if ($element = $element_class::model()->find('event_id = ?',array($event->id))) {
					$elements[] = $element;
				}
			}
		} else {
			$criteria->compare('`default`',1);

			foreach (ElementType::model()->findAll($criteria) as $element_type) {
				$element_class = $element_type->class_name;

				$elements[] = new $element_class;
			}
		}

		return $elements;
	}

	/**
	 * Get the optional elements for the current module's event type
	 * This will be overriden by the module
	 *
	 * @return array
	 */
	public function getOptionalElements($action) {
		switch ($action) {
			case 'create':
			case 'view':
				return array();
			case 'update':
				$event_type = EventType::model()->findByPk($this->event->event_type_id);

				$criteria = new CDbCriteria;
				$criteria->compare('event_type_id',$event_type->id);
				$criteria->compare('`default`',1);
				$criteria->order = 'display_order asc';

				$elements = array();
				foreach (ElementType::model()->findAll($criteria) as $element_type) {
					$element_class = $element_type->class_name;
					if (!$element_class::model()->find('event_id = ?',array($this->event->id))) {
						$elements[] = new $element_class;
					}
				}
				
				return $elements;
		}
	}

	public function actionCreate() {
		$this->event_type = EventType::model()->find('class_name=?', array($this->getModule()->name));
		if (!$this->patient = Patient::model()->findByPk($_REQUEST['patient_id'])) {
			throw new CHttpException(403, 'Invalid patient_id.');
		}

		// firm changing sanity
		if (!empty($_POST) && !empty($_POST['firm_id']) && $_POST['firm_id'] != $this->firm->id) {
			// The firm id in the firm is not the same as the session firm id, e.g. they've changed
			// firms in a different tab. Set the session firm id to the provided firm id.

			$session = Yii::app()->session;

			$firms = $session['firms'];
			$firmId = intval($_POST['firm_id']);

			if ($firms[$firmId]) {
				$session['selected_firm_id'] = $firmId;
				$this->selectedFirmId = $firmId;
				$this->firm = Firm::model()->findByPk($this->selectedFirmId);
			} else {
				// They've supplied a firm id in the post to which they are not entitled??
				throw new Exception('Invalid firm id on attempting to create event.');
			}
		}

		$elements = $this->getDefaultElements($this->event_type->id);

		if (!count($elements)) {
			throw new CHttpException(403, 'Gadzooks!	I got me no elements!');
		}

		if (!empty($_POST)) {
			$elementList = array();

			// validation
			foreach ($elements as $element) {
				$elementClassName = get_class($element);
				$element->attributes = Helper::convertNHS2MySQL($_POST[$elementClassName]);
				$elementList[] = $element;
				if (!$element->validate()) {
					foreach ($element->getErrors() as $errormsgs) {
						foreach ($errormsgs as $error) {
							$index = preg_replace('/^Element/','',$elementClassName);
							$errors[$index][] = $error;
						}
					}
				}
			}

			// creation
			if (empty($errors)) {
				// The user has submitted the form to create the event
				$eventId = $this->createElements(
					$elements, $_POST, $this->firm, $this->patient->id, Yii::app()->user->id, $this->event_type->id
				);

				if ($eventId) {
					$this->logActivity('created event.');

					Yii::app()->user->setFlash('success', "{$this->event_type->name} created.");
					$this->redirect(array('Default/view/'.$eventId));
					return;
				}
			}
		}

		$this->editable = false;
		$this->title = 'Create';

		$this->renderPartial(
			'create',
			array('elements' => $this->getDefaultElements(), 'eventId' => null, 'errors' => @$errors),
			false, true
		);

	}

	public function actionView($id) {
		if (!$this->event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}

		$this->patient = $this->event->episode->patient;

		$this->event_type = EventType::model()->findByPk($this->event->event_type_id);

		$elements = $this->getDefaultElements();

		// Decide whether to display the 'edit' button in the template
		if ($this->firm->serviceSubspecialtyAssignment->subspecialty_id !=
			$this->event->episode->firm->serviceSubspecialtyAssignment->subspecialty_id) {
			$this->editable = false;
		} else {
			$this->editable = true;
		}

		$currentSite = Site::model()->findByPk(Yii::app()->request->cookies['site_id']->value);

		$this->logActivity('viewed event');

		$this->title = $this->event_type->name;

		$this->renderPartial(
			'view', array(
			'elements' => $elements,
			'eventId' => $id,
			), false, true);
	}

	public function actionUpdate($id) {
		if (!$this->event = Event::model()->findByPk($id)) {
			throw new CHttpException(403, 'Invalid event id.');
		}

		// Check the user's firm is of the correct subspecialty to have the
		// rights to update this event
		if ($this->firm->serviceSubspecialtyAssignment->subspecialty_id !=
			$this->event->episode->firm->serviceSubspecialtyAssignment->subspecialty_id) {
			throw new CHttpException(403, 'The firm you are using is not associated with the subspecialty for this event.');
		}

		$this->event_type = EventType::model()->findByPk($this->event->event_type_id);
		$this->patient = $this->event->episode->patient;

		// firm changing sanity
		if (!empty($_POST) && !empty($_POST['firm_id']) && $_POST['firm_id'] != $this->firm->id) {
			// The firm id in the firm is not the same as the session firm id, e.g. they've changed
			// firms in a different tab. Set the session firm id to the provided firm id.

			$session = Yii::app()->session;

			$firms = $session['firms'];
			$firmId = intval($_POST['firm_id']);

			if ($firms[$firmId]) {
				$session['selected_firm_id'] = $firmId;
				$this->selectedFirmId = $firmId;
				$this->firm = Firm::model()->findByPk($this->selectedFirmId);
			} else {
				// They've supplied a firm id in the post to which they are not entitled??
				throw new Exception('Invalid firm id on attempting to update event.');
			}
		}

		$elements = $this->getDefaultElements();

		if (!count($elements)) {
			throw new CHttpException(403, 'Gadzooks!	I got me no elements!');
		}

		if (!empty($_POST)) {
			if (isset($_POST['cancel'])) {
				$this->redirect(array('Default/view/'.$this->event->id));
				return;
			}

			$elementList = array();

			// validation
			foreach ($elements as $element) {
				$elementClassName = get_class($element);
				$element->attributes = Helper::convertNHS2MySQL($_POST[$elementClassName]);
				$elementList[] = $element;
				if (!$element->validate()) {
					foreach ($element->getErrors() as $errormsgs) {
						foreach ($errormsgs as $error) {
							$index = preg_replace('/^Element/','',$elementClassName);
							$errors[$index][] = $error;
						}
					}
				}
			}

			// creation
			if (empty($errors)) {
				$success = $this->updateElements($elements, $_POST, $this->event);

				if ($success) {
					$this->logActivity('updated event');

					// Update event to indicate user has made a change
					$this->event->datetime = date("Y-m-d H:i:s");
					$this->event->user = Yii::app()->user->id;
					if (!$this->event->save()) {
						throw new SystemException('Unable to update event: '.print_r($this->event->getErrors(),true));
					}

					OELog::log("Updated event {$this->event->id}");

					$this->redirect(array('Default/view/'.$this->event->id));
					return;
				}
			}
		}

		$this->editing = true;
		$this->title = 'Update';

		$this->renderPartial(
			'update',
			array(
				'elements' => $this->getDefaultElements(),
				'errors' => @$errors
			),
			false, true
		);
	}

	public function renderDefaultElements($action, $form=false, $data=false) {
		foreach ($this->getDefaultElements() as $element) {
			$this->renderPartial(
				$action . '_' . get_class($element),
				array('element' => $element, 'data' => $data, 'form' => $form),
				false, true
			);
		}
	}

	public function renderOptionalElements($action, $form=false,$data=false) {
		foreach ($this->getOptionalElements($action) as $element) {
			$this->renderPartial(
				$action . '_' . get_class($element),
				array('element' => $element, 'data' => $data, 'form' => $form),
				false, true
			);
		}
	}

	public function header($editable=false) {
		$episodes = $this->patient->episodes;

		if (!Yii::app()->params['enabled_modules'] || !is_array(Yii::app()->params['enabled_modules'])) {
			$eventTypes = array();
		} else {
			$eventTypes = EventType::model()->findAll("class_name in ('".implode("','",Yii::app()->params['enabled_modules'])."')");
		}

		$this->renderPartial('//patient/event_header',array(
			'episodes'=>$episodes,
			'eventTypes'=>$eventTypes,
			'model'=>$this->patient,
			'editable'=>$editable
		));
	}

	public function footer() {
		$episodes = $this->patient->episodes;

		if (!Yii::app()->params['enabled_modules'] || !is_array(Yii::app()->params['enabled_modules'])) {
			$eventTypes = array();
		} else {
			$eventTypes = EventType::model()->findAll("class_name in ('".implode("','",Yii::app()->params['enabled_modules'])."')");
		}

		$this->renderPartial('//patient/event_footer',array(
			'episodes'=>$episodes,
			'eventTypes'=>$eventTypes
		));
	}

	public function createElements($elements, $data, $firm, $patientId, $userId, $eventTypeId)
	{
		$valid = true;
		$elementsToProcess = array();

		// Go through the array of elements to see which the user is attempting to
		// create, which are required and whether they pass validation.
		foreach ($elements as $element) {
			$elementClassName = get_class($element);

			if ($element->required || isset($data[$elementClassName])) {
				if (isset($data[$elementClassName])) {
					$element->attributes = Helper::convertNHS2MySQL($data[$elementClassName]);
				}

				if (!$element->validate()) {
					$valid = false;
				} else {
					$elementsToProcess[] = $element;
				}
			}
		}

		if (!$valid) {
			return false;
		}

		/**
		 * Create the event. First check to see if there is currently an episode for this
		 * subspecialty for this patient. If so, add the new event to it. If not, create an
		 * episode and add it to that.
		 */
		$episode = $this->getOrCreateEpisode($firm, $patientId);
		$event = $this->createEvent($episode, $userId, $eventTypeId);

		// Create elements for the event
		foreach ($elementsToProcess as $element) {
			$element->event_id = $event->id;

			// No need to validate as it has already been validated and the event id was just generated.
			if (!$element->save(false)) {
				throw new Exception('Unable to save element ' . get_class($element) . '.');
			}
		}

		return $event->id;
	}

	/**
	 * Update elements based on arrays passed over from $_POST data
	 *
	 * @param array		$elements		array of SiteElementTypes
	 * @param array		$data			$_POST data to update
	 * @param object $event				the associated event
	 *
	 * @return boolean $success		true if all elements suceeded, false otherwise
	 */
	public function updateElements($elements, $data, $event)
	{
		$success = true;
		$toDelete = array();
		$toSave = array();

		foreach ($elements as $element) {
			$elementClassName = get_class($element);
			$needsValidation = false;

			if (isset($data[$elementClassName])) {
				$element->attributes = Helper::convertNHS2MySQL($data[$elementClassName]);

				$toSave[] = $element;

				$needsValidation = true;
			} elseif ($element->required) {
				// The form has failed to provide an array of data for a required element.
				// This isn't supposed to happen - a required element should at least have the
				// $data[$elementClassName] present, even if there's nothing in it.
				$success = false;
			} elseif ($element->event_id) {
				// This element already exists, isn't required and has had its data deleted.
				// Therefore it needs to be deleted.
				$toDelete[] = $element;
			}

			if ($needsValidation) {
				if (!$element->validate()) {
					$success = false;
				}
			}
		}

		if (!$success) {
			// An element failed validation or a required element didn't have an
			// array of data provided for it.
			return false;
		}

		foreach ($toSave as $element) {
			if (!isset($element->event_id)) {
				$element->event_id = $event->id;
			}

			if (!$element->save()) {
				OELog::log("Unable to save element: $element->id ($elementClassName): ".print_r($element->getErrors(),true));
				throw new SystemException('Unable to save element: '.print_r($element->getErrors(),true));
			}
		}

		foreach ($toDelete as $element) {
			$element->delete();
		}

		return true;
	}

	public function getOrCreateEpisode($firm, $patientId)
	{
		$subspecialtyId = $firm->serviceSubspecialtyAssignment->subspecialty->id;
		$episode = Episode::model()->getBySubspecialtyAndPatient($subspecialtyId, $patientId);

		if (!$episode) {
			$episode = new Episode();
			$episode->patient_id = $patientId;
			$episode->firm_id = $firm->id;
			$episode->start_date = date("Y-m-d H:i:s");

			if (!$episode->save()) {
				OELog::log("Unable to create new episode for patient_id=$episode->patient_id, firm_id=$episode->firm_id, start_date='$episode->start_date'");
				throw new Exception('Unable to create create episode.');
			}

			OELog::log("New episode created for patient_id=$episode->patient_id, firm_id=$episode->firm_id, start_date='$episode->start_date'");

			if (Yii::app()->params['use_pas']) {
				// Try to fetch a referral from PAS for this episode
				$episode->fetchPASReferral();
			}
		}

		return $episode;
	}

	public function createEvent($episode, $userId, $eventTypeId)
	{
		$event = new Event();
		$event->episode_id = $episode->id;
		$event->event_type_id = $eventTypeId;
		$event->datetime = date("Y-m-d H:i:s");
		if (!$event->save()) {
			OELog::log("Failed to creat new event for episode_id=$episode->id, event_type_id=$eventTypeId, datetime='$event->datetime'");
			throw new Exception('Unable to save event.');
		}

		OELog::log("Created new event for episode_id=$episode->id, event_type_id=$eventTypeId, datetime='$event->datetime'");

		return $event;
	}

	public function displayErrors($errors) {
		$this->renderPartial('//elements/form_errors',array('errors'=>$errors));
	}

	public function init() {
		$this->cssPath = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.css'),true);
		$this->jsPath = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.js'),true);
		$this->imgPath = Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('application.modules.'.$this->getModule()->name.'.img'),true).'/';
		parent::init();
	}

	public function actionLoadElement() {
		if (!$element = ElementType::model()->find('class_name = ?',@$_GET['element_name'])) {
			throw new SystemException('Element not found: '.@$_GET['element_name']);
		}

		$this->renderPartial(
			'create' . '_' . get_class($element),
			array('element' => $element, 'data' => array(), 'form' => null),
			false, true
		);
	}
}
