<?php

return [
    '*' => [
        'pluginName' => 'Forms',
        'defaultPage' => 'forms',

        // Forms
        'defaultFormTemplate' => '',
        'defaultEmailTemplate' => '',
        'enableUnloadWarning' => true,
        'enableBackSubmission' => true,
        'ajaxTimeout' => 10,

        // General Fields
        'disabledFields' => [],
        'defaultLabelPosition' => 'above-input',
        'defaultInstructionsPosition' => 'below-input',

        // Fields
        'defaultFileUploadVolume' => '',
        'defaultDateDisplayType' => '',
        'defaultDateValueOption' => '',
        'defaultDateTime' => '',

        // Submissions
        'maxIncompleteSubmissionAge' => 30,
        'enableCsrfValidationForGuests' => true,
        'useQueueForNotifications' => false,
        'useQueueForIntegrations' => false,
        'queuePriority' => null,

        // Sent Notifications
        'sentNotifications' => true,
        'maxSentNotificationsAge' => 30,

        // Spam
        'saveSpam' => true,
        'spamLimit' => 500,
        'spamEmailNotifications' => false,
        'spamBehaviour' => 'showSuccess',
        'spamKeywords' => '',
        'spamBehaviourMessage' => '',

        // Alerts
        'sendEmailAlerts' => false,
        'alertEmails' => [],

        // PDFs
        'pdfPaperSize' => 'letter',
        'pdfPaperOrientation' => 'portrait',

        // Theme
        'themeConfig' => [],
    ]
];