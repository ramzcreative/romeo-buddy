<?php
/**
 * craftcms/contact-form settings
 *
 * toEmail is required by the plugin's own Settings::defineRules() —
 * without it, Mailer::send() throws "The Contact Form settings don't
 * validate." on every submission, before the honeypot/reCAPTCHA checks
 * in modules/contactform (craft-modules) even run.
 *
 * A literal, not an env var — it's not a secret, and env-indirecting it
 * was the original cause of a production outage (the var was simply never
 * set on this site's server, and nothing failed loudly until a real
 * submission hit it). Submissions are tracked via the CP's Contact Form
 * Extensions submissions list, not this inbox, but the setting itself
 * still has to be present and valid for the base plugin to function.
 */
return [
    'toEmail' => 'publishing@ramzcreative.com',
];
