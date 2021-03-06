<?php

namespace Spatie\EmailCampaigns\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EmailCampaigns\Enums\EmailCampaignStatus;
use Spatie\EmailCampaigns\Exceptions\CampaignCouldNotBeSent;
use Spatie\EmailCampaigns\Exceptions\CampaignCouldNotBeUpdated;
use Spatie\EmailCampaigns\Jobs\SendCampaignJob;

class EmailCampaign extends Model
{
    protected $guarded = [];

    public $casts = [
        'track_opens' => 'bool',
        'track_clicks' => 'boolean',
        'open_rate' => 'integer',
        'click_rate' => 'integer',
        'send_to_number_of_subscribers' => 'integer',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function(EmailCampaign $emailCampaign) {
            $emailCampaign->status = EmailCampaignStatus::CREATED;
        });
    }

    public function emailList(): BelongsTo
    {
        return $this->belongsTo(EmailList::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(CampaignLink::class);
    }

    public function sends(): HasMany
    {
        return $this->hasMany(EmailCampaignSend::class);
    }

    public function trackOpens()
    {
        $this->ensureUpdatable();

        $this->update(['track_opens' => true]);

        return $this;
    }

    public function trackClicks()
    {
        $this->ensureUpdatable();

        $this->update(['track_clicks' => true]);

        return $this;
    }

    public function to(EmailList $emailList)
    {
        $this->ensureUpdatable();

        $this->email_list_id = $emailList->id;

        return $this;
    }

    public function send(): void
    {
        $this->ensureSendable();

        dispatch(new SendCampaignJob($this, $this->emai));

    }

    public function sendTo(EmailList $emailList): void
    {
        $this->to($emailList)->send();
    }

    protected function ensureSendable()
    {
        if ($this->status === EmailCampaignStatus::SENDING) {
            throw CampaignCouldNotBeSent::beingSent($this);
        }

        if ($this->status === EmailCampaignStatus::SENT) {
            throw CampaignCouldNotBeSent::alreadySent($this);
        }

        if (is_null($this->emailList)) {
            throw CampaignCouldNotBeSent::noListSet($this);
        }
    }

    protected function ensureUpdatable(): void
    {
        if ($this->status === EmailCampaignStatus::SENDING) {
            throw CampaignCouldNotBeUpdated::beingSent($this);
        }

        if ($this->status === EmailCampaignStatus::SENT) {
            throw CampaignCouldNotBeSent::alreadySent($this);
        }
    }
}

