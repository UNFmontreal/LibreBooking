<?php

// English texts of the MRI emails, messages and link labels. Other languages fall back to this file.

return [
    'message.regex_file_error' => 'ERROR in REGEX file - contact Support!',
    'text.screening_link_pre' => 'Lien vers le dépistage / Link to Screening :',
    'text.screening_link' => 'UNF - Screening/Dépistage',
    'text.completed_survey' => 'complété / completed',
    'text.not_completed_survey' => 'non complété / not completed',
    'text.no_screening_link' => 'Pas de dépistage/No Screening Link',
    'text.requisition_link' => '',
    'text.admin_link' => '',
    'text.logbook_link' => 'Link to logbook',
    'text.screening_not_completed' => '
Merci de compléter, ou faire compléter par votre participant, le dépistage suivant {LINK} aussi tôt que possible.<br>
Dès que le dépistage est complété, notre équipe pourra s’assurer qu’il n’y a pas d’incompatibilités majeures et ainsi vous permettre une flexibilité dans le temps pour annuler votre réservation sans frais dans les délais requis.
<br><br>
Please complete, or ask your participant, to complete the screening using the following link {LINK} as soon as possible.<br>
As soon as the screening is completed, our team will be able to ensure that there are no major incompatibilities and thus allow you flexibility in time to cancel your reservation free of charge within the required deadlines.
',
    'text.requisition_not_completed' => '',
    'text.logbook_not_completed' => '',
    'email.subject' => '',
    'email.body' => '',
    'email.screening_missing_subject' => 'Screening missing',
    'email.screening_missing_body' => '
Bonjour/Hi {FIRST} {LAST},<br>
<br>
Vous avez effectué la réservation de l\'IRM ci-dessous / You have booked the following upcoming MRI reservation.<br>
<br>
Date de réservation/Reservation date:  {DATE}<br>
Date de fin/Start time: {START}<br>
Date de début/End time: {END}<br>
Nom de session/Session name: {SESSION}<br>
<br>
{SCREENING_NOT_COMPLETED}<br>
<br>
Merci, Thank you<br>
l\'équipe UNF / UNF Team',
    'email.delete_subject' => 'Annulation de reservation UNF/Cancel reservation UNF',
    'email.delete_body' => '
Bonjour/Hi,<br>
<br>

Date de reservation/Reservation date:  {DATE}<br>
Date de fin/Start time: {START}<br>
Date de début/End time: {END}<br>
Nom de session/Session name: {SESSION}<br>
<br>
{NAME} ({EMAIL}) has deleted a reservation less than seven days prior to this reservation.<br>
An entry in the logbook has been filled therefore this reservation should be billed if nobody took the slot.<br>
Token: {TOKEN}<br><br>
l\'équipe UNF / UNF Team',
    'email.logbook_subject' => 'Logbook entry missing',
    'email.logbook_body' => '
Bonjour/Hi,<br>
<br>
Le logbook de la reservation pour la session d\'IRM ci-dessous n’a toujours pas été rempli.<br><br>
Date de reservation/Reservation date:  {DATE}<br>
Date de fin/Start time: {START}<br>
Date de début/End time: {END}<br>
Nom de session/Session name: {SESSION}<br>
Token: {TOKEN}<br>
<br>
Merci de compléter, le logbook {LINK} aussi tôt que possible.
',
    'email.birthday_not_matching_subject' => '',
    'email.birthday_not_matching_body' => '',
    'message.session_name_error' => 'You must include a valid session name. If needed, please contact support.unf@criugm.qc.ca.',
    'message.session_exists_error' => 'Cette session existe déjà. Si nécessaire contactez support.unf@criugm.qc.ca.
<br>This session already exists. If needed, please contact support.unf@criugm.qc.ca.',
    'message.phantom_multi_error' => 'The session name you provided is not compatible with a phantom acquisition. If needed, please contact support.unf@criugm.qc.ca.',
    'message.not_allowed_recurring' => 'You are not allowed to use recurrent reservation. If needed, please contact support.unf@criugm.qc.ca.',
    'message.not_allowed_off_hours' => 'You are not allowed to make reservations during Off-Hours. If needed, please contact support.unf@criugm.qc.ca.',
    'message.change_resource' => 'You are not allowed to change the resource. If needed, please contact support.unf@criugm.qc.ca.',
    'message.duplicated_token' => '',
    'message.ethic_number_mismatch' => '',
    'message.not_allowed_update' => '
Il n\'est pas possible de modifier la réservation car le nom de session a changé. Si nécessaire, contactez support.unf@criugm.qc.ca.
It is not possible you to modify a reservation because the session name has changed. If needed, please contact support.unf@criugm.qc.ca.',
    'message.not_allowed_update_multix_no_screening' => '
Vous ne pouvez pas mettre à jour votre réservation car le dépistage n\'existe pas. Si nécessaire, contactez support.unf@criugm.qc.ca.
This reservation cannot be updated because your participant\'s screening does not exist. If needed, please contact support.unf@criugm.qc.ca.',
    'message.screening_does_not_exist' => '
Vous ne pouvez pas mettre à jour votre réservation car le dépistage n\'existe pas. Si nécessaire, contactez support.unf@criugm.qc.ca.
You cannot update your reservation because your participant\'s screening does not exist. If needed, please contact support.unf@criugm.qc.ca.',
    'message.screening_does_not_exist_for_multi' => 'You cannot make a recurrent reservation because your participant\'s screening does not exist. If needed, please contact support.unf@criugm.qc.ca.',
    'message.multix_update_session_no_screening' => 'This reservation cannot be updated because your participant\'s screening does not exist. If needed, please contact support.unf@criugm.qc.ca.',
    'message.not_multi_name_change_screening_complete' => 'You cannot update this reservation because your participant\'s screening has already been completed. If needed, please contact support.unf@criugm.qc.ca.',
    'message.not_multi_name_change_participant_exists' => 'You cannot update this reservation because your new participant already exists. If needed, please contact support.unf@criugm.qc.ca.',
    'message.not_allow_modification_is_phantom' => 'This reservation cannot be updated because you try to update a not phantom reservation to a Phantom reservation. If needed, please contact support.unf@criugm.qc.ca',
    'message.not_allowed_delete_multi1' => 'We do not allow you to delete this reservation. If needed, please contact support.unf@criugm.qc.ca.',
    'message.not_allowed_delete_multi1_admin' => 'It was a Multi1.<br>Please be careful: either re-create a new reservation for this participant or update another reservation with the same participant.<br>It will update from MultiX->Mult1.',
    'message.screening_session_name_required' => 'A session name is required for this resource.',
    'message.screening_phantom_multi' => 'Phantom reservations cannot use a multi-session study.',
    'message.screening_recurring' => 'Recurring reservations are only allowed for multi-session studies.',
    'message.screening_duplicate_participant' => 'This session name is already in use.',
    'message.screening_resource_change' => 'The resource of a screened reservation cannot be changed.',
    'message.screening_unavailable' => 'The screening system is unavailable. Please try again later.',

    // Labels and descriptions of the settings in the admin configuration page
    'config.ls.base.url' => 'LimeSurvey Base URL',
    'config.ls.base.url.description' => 'Base URL of the LimeSurvey installation, without /index.php',
    'config.ls.user' => 'LimeSurvey RPC User',
    'config.ls.user.description' => 'LimeSurvey user used for the remote control API (needs admin access)',
    'config.ls.password' => 'LimeSurvey RPC Password',
    'config.ls.password.description' => 'Password of the LimeSurvey remote control user (prefer the environment variable)',
    'config.ls.screening.survey.id' => 'Screening Survey ID',
    'config.ls.screening.survey.id.description' => 'LimeSurvey survey ID that holds the screening participants',
    'config.ls.logbook.survey.id' => 'Logbook Survey ID',
    'config.ls.logbook.survey.id.description' => 'LimeSurvey survey ID of the logbook',
    'config.resource.names' => 'Monitored Resources',
    'config.resource.names.description' => 'Comma separated names of the resources whose reservations are screened. Leave empty to disable the plugins',
    'config.attribute.session.name.label' => 'Session Name Attribute Label',
    'config.attribute.session.name.label.description' => 'Label of the reservation custom attribute that holds the session name',
    'config.attribute.phantom.label' => 'Phantom Attribute Label',
    'config.attribute.phantom.label.description' => 'Label of the reservation custom attribute that marks a phantom reservation',
    'config.attribute.unf.technical.issue.label' => 'Technical Issue Attribute Label',
    'config.attribute.unf.technical.issue.label.description' => 'Label of the technical issue checkbox. A technical issue cancels the screening of the reservation',
    'config.attribute.screening.link.label' => 'Screening Link Attribute Label',
    'config.attribute.screening.link.label.description' => 'Label of the attribute holding the screening link',
    'config.attribute.screening.valid.label' => 'Screening Valid Attribute Label',
    'config.attribute.screening.valid.label.description' => 'Label of the checkbox that marks a phantom reservation as validated',
    'config.attribute.mri.operator.label' => 'MRI Operator Attribute Label',
    'config.attribute.mri.operator.label.description' => 'Label of the attribute holding the MRI operator',
    'config.attribute.research.assistant.label' => 'Research Assistant Attribute Label',
    'config.attribute.research.assistant.label.description' => 'Label of the attribute holding the research assistant',
    'config.attribute.logbook.link.label' => 'Logbook Link Attribute Label',
    'config.attribute.logbook.link.label.description' => 'Label of the admin-only attribute holding the link to the logbook entry',
    'config.studies.url' => 'Studies JSON URL',
    'config.studies.url.description' => 'URL of the studies JSON file that defines session names and multi-session studies',
    'config.studies.cache.ttl.seconds' => 'Studies Cache Duration',
    'config.studies.cache.ttl.seconds.description' => 'Seconds the downloaded studies file is cached. Use 0 to download it for every validation',
    'config.mri.operators' => 'MRI Operators',
    'config.mri.operators.description' => 'JSON object that maps the display names of the MRI operators to their LibreBooking user IDs',
    'config.cron.look.ahead.days' => 'Cron Look Ahead Days',
    'config.cron.look.ahead.days.description' => 'Days the cron jobs look ahead for pending reservations',
    'config.max.delay.days.to.cancel' => 'Free Cancellation Days',
    'config.max.delay.days.to.cancel.description' => 'Days before a reservation that can be cancelled without charge',
    'config.email.date.format' => 'Email Date Format',
    'config.email.date.format.description' => 'PHP date format of the dates in emails',
    'config.email.time.format' => 'Email Time Format',
    'config.email.time.format.description' => 'PHP date format of the times in emails',
    'config.support.email' => 'Support Email',
    'config.support.email.description' => 'Support email address',
    'config.support.email.name' => 'Support Name',
    'config.support.email.name.description' => 'Support display name',
    'config.logbook.operator.address' => 'Logbook Reminder Address',
    'config.logbook.operator.address.description' => 'Address that receives the reminders about incomplete logbook entries, for example the email address of a Slack channel',
    'config.link.to.logbook' => 'Logbook Link Template',
    'config.link.to.logbook.description' => 'Template of the link to the logbook entry. Placeholders: [LS_BASEURL], [LogbookSurveyID], [TOKEN], [session_name], [RA], [START_TIME], [END_TIME], [DATE], [ETHIC], [URL_ID], [URL_ID2]',
];
