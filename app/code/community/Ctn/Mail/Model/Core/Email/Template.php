<?php
/**
 * Use of gmail as SMTP server requires to lower security on https://www.google.com/settings/security/lesssecureapps !!!
 * There are some email settings in: 
 *   System > Configuration > Advanced - System / Mail Sending Settings
 * 
 * @category Ctn
 * @package  Ctn_Mail
 * @author   Constantin Nita <ctinnita@gmail.com>
 */
class Ctn_Mail_Model_Core_Email_Template extends Mage_Core_Model_Email_Template 
{
    // SMTP
    const XML_PATH_SMTP = 'ctnmail/smtp';
    const XML_PATH_SMTP_ENABLED = 'ctnmail/smtp/enabled';
    // Copy Emails
    const XML_PATH_COPY_EMAILS = 'ctnmail/copy_emails/copy_to';
    
    /**
     * Send mail to recipient
     *
     * @param   array|string       $email        E-mail(s)
     * @param   array|string|null  $name         receiver name(s)
     * @param   array              $variables    template variables
     * @return  boolean
     */
    public function send($email, $name = null, array $variables = array())
    {
        // [ctn] start        
        $smtpEnabled = Mage::getStoreConfigFlag(self::XML_PATH_SMTP_ENABLED);
        $copyEmails = trim(Mage::getStoreConfig(self::XML_PATH_COPY_EMAILS));
        // [ctn] end
        
        if (!$this->isValidForSend()) {
            Mage::logException(new Exception('This letter cannot be sent.')); // translation is intentionally omitted
            return false;
        }

        $emails = array_values((array)$email);
        $names = is_array($name) ? $name : (array)$name;
        $names = array_values($names);
        foreach ($emails as $key => $email) {
            if (!isset($names[$key])) {
                $names[$key] = substr($email, 0, strpos($email, '@'));
            }
        }
        // [ctn] start        
        if (0 < strlen($copyEmails)) {
            $copyEmails = explode(',', $copyEmails);
            $key = count($emails);
            foreach ($copyEmails as $increment => $email) {
                $emails[$key + $increment] = $email;
                $names[$key + $increment] = substr($email, 0, strpos($email, '@'));
            }
        }
        // [ctn] end

        $variables['email'] = reset($emails);
        $variables['name'] = reset($names);

        $this->setUseAbsoluteLinks(true);
        $text = $this->getProcessedTemplate($variables, true);
        $subject = $this->getProcessedTemplateSubject($variables);

        $setReturnPath = Mage::getStoreConfig(self::XML_PATH_SENDING_SET_RETURN_PATH);
        switch ($setReturnPath) {
            case 1:
                $returnPathEmail = $this->getSenderEmail();
                break;
            case 2:
                $returnPathEmail = Mage::getStoreConfig(self::XML_PATH_SENDING_RETURN_PATH_EMAIL);
                break;
            default:
                $returnPathEmail = null;
                break;
        }

        // @todo drop this if you want to use email queue
        if (!$smtpEnabled) {
            if ($this->hasQueue() && $this->getQueue() instanceof Mage_Core_Model_Email_Queue) {
                /** @var $emailQueue Mage_Core_Model_Email_Queue */
                $emailQueue = $this->getQueue();
                $emailQueue->setMessageBody($text);
                $emailQueue->setMessageParameters(array(
                        'subject'           => $subject,
                        'return_path_email' => $returnPathEmail,
                        'is_plain'          => $this->isPlain(),
                        'from_email'        => $this->getSenderEmail(),
                        'from_name'         => $this->getSenderName(),
                        'reply_to'          => $this->getMail()->getReplyTo(),
                        'return_to'         => $this->getMail()->getReturnPath(),
                    ))
                    ->addRecipients($emails, $names, Mage_Core_Model_Email_Queue::EMAIL_TYPE_TO)
                    ->addRecipients($this->_bccEmails, array(), Mage_Core_Model_Email_Queue::EMAIL_TYPE_BCC);
                $emailQueue->addMessageToQueue();
                
                return true;
            }
        }

        ini_set('SMTP', Mage::getStoreConfig('system/smtp/host'));
        ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port'));

        $mail = $this->getMail();

        if ($returnPathEmail !== null) {
            $mailTransport = new Zend_Mail_Transport_Sendmail("-f".$returnPathEmail);
            Zend_Mail::setDefaultTransport($mailTransport);
        }

        foreach ($emails as $key => $email) {
            $mail->addTo($email, '=?utf-8?B?' . base64_encode($names[$key]) . '?=');
        }

        if ($this->isPlain()) {
            $mail->setBodyText($text);
        } else {
            $mail->setBodyHTML($text);
        }

        $mail->setSubject('=?utf-8?B?' . base64_encode($subject) . '?=');
        $mail->setFrom($this->getSenderEmail(), $this->getSenderName());
        
        try {        
            // [ctn] start
            if ($smtpEnabled) {
                $smtpConfig = Mage::getStoreConfig(self::XML_PATH_SMTP);
                $emailConfig = array(
                    'auth' => strtolower($smtpConfig['auth']),
                    'ssl' => strtolower($smtpConfig['ssl']),
                    'port' => $smtpConfig['port'],
                    'username' => $smtpConfig['username'],
                    'password' => $smtpConfig['password']
                );
                $smtpHost = strtolower($smtpConfig['smtp_host']);

                if ($returnPathEmail !== null) {
                    $mail->setReturnPath($returnPathEmail);
                    $mail->setReplyTo($returnPathEmail);
                }

                $transport = new Zend_Mail_Transport_Smtp($smtpHost, $emailConfig);
                $mail->send($transport);                    
                $this->_mail = null;
            // [ctn] end
            } else {                
                $mail->send();
                $this->_mail = null;                
            }            
        }
        catch (Exception $e) {
            $this->_mail = null;
            Mage::logException($e);
            //Mage::log($e->getMessage());
            return false;
        }

        return true;
    }        
}