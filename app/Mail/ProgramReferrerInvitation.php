<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;
class ProgramReferrerInvitation extends Mailable {
 public function __construct(public string $programName,public string $recipientName,public string $inviteUrl,public bool $needsSetup){}
 public function build(){return $this->subject('Invitation to '.$this->programName)->view('emails.program-referrer-invitation');}
}
