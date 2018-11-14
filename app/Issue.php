<?php

namespace App;

use App\IssueTrackers\Bitbucket\Bitbucket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Issue extends Model
{
    const STATUS_NEW     = 1;
    const STATUS_OPEN    = 2;
    const STATUS_HOLD    = 3;
    const STATUS_RESOLVED= 4;
    const STATUS_CLOSED  = 5;
    const STATUS_INVALID = 6;

    const PRIORITY_TRIVIAL  = 1;
    const PRIORITY_MINOR    = 2;
    const PRIORITY_MAJOR    = 3;
    const PRIORITY_CRITICAL = 4;
    const PRIORITY_BLOCKER  = 5;

    const TYPE_TASK         = 1;
    const TYPE_BUG          = 2;
    const TYPE_ENHANCEMENT  = 3;
    const TYPE_PROPOSAL     = 4;

    protected $guarded = [];

    use Taggable;

    public static function fromBitbucketIssue($repository, $issue)
    {
        return Issue::updateOrCreate([
            'repository_id' => $repository->id,
            'issue_id' => $issue->local_id ?? $issue->id,
        ], [
            'username' => $issue->responsible->username ?? ($issue->assignee->username ?? null),
            'title'    => str_limit($issue->title, 255),
            'status'   => Issue::parseStatus($issue->status ?? $issue->state),
            'priority' => Issue::parsePriority($issue->priority),
            'type'     => Issue::parseType($issue->metadata->kind ?? $issue->kind),
        ]);
    }

    public function updateBitbucketIssue()
    {
        (new Bitbucket)->updateIssue($this->repository->account, $this->repository->repo, $this->issue_id, [
            'assigne' => [
                'username' => $this->username,
            ],
            'title'    => $this->title,
            'status'   => array_flip(static::statuses())[$this->status],
            'priority' => array_flip(static::priorities())[$this->priority],
            'type'     => array_flip(static::types())[$this->type],
        ]);
    }

    public function update(array $attributes = [], array $options = [])
    {
        tap ($attributes['tags'], function($tags) use(&$attributes){
            unset($attributes['tags']);
            $this->syncTags($tags);
        });
        return tap(parent::update($attributes, $options), function(){
            $this->updateBitbucketIssue();
        });
    }


    public function repository()
    {
        return $this->belongsTo(Repository::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public static function statuses()
    {
        return [
            'new'     => static::STATUS_NEW     ,
            'open'    => static::STATUS_OPEN    ,
            'hold'    => static::STATUS_HOLD    ,
            'resolved'=> static::STATUS_RESOLVED    ,
            'closed'  => static::STATUS_CLOSED  ,
            'invalid' => static::STATUS_INVALID ,
        ];
    }
    public static function parseStatus($statusName)
    {
        return static::statuses()[$statusName];
    }

    public static function priorities()
    {
        return [
            'trivial'  => static::PRIORITY_TRIVIAL ,
            'minor'    => static::PRIORITY_MINOR   ,
            'major'    => static::PRIORITY_MAJOR   ,
            'critical' => static::PRIORITY_CRITICAL,
            'blocker'  => static::PRIORITY_BLOCKER ,
        ];
    }

    public static function parsePriority($priority)
    {
        return static::priorities()[$priority];
    }

    public static function types()
    {
        return [
            'task'        => static::TYPE_TASK,
            'bug'         => static::TYPE_BUG,
            'enhancement' => static::TYPE_ENHANCEMENT,
            'proposal'    => static::TYPE_PROPOSAL,
        ];
    }

    public static function parseType($kind)
    {
        return static::types()[$kind];
    }
}
