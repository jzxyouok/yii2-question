<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */
namespace yuncms\question\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\ActiveQuery;
use yii\helpers\Markdown;
use yii\helpers\HtmlPurifier;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;

/**
 * Answer Model
 * @package yuncms\question
 *
 * @property integer $id
 * @property integer $user_id 用户ID
 * @property integer $question_id 问题ID
 * @property string $content 回答内容
 * @property integer $comments 评论数
 * @property integer $supports 赞数
 * @property integer $adopted_at 采纳时间
 * @property integer $created_at 回答时间
 * @property integer $updated_at 更新时间
 *
 * @property Question $question
 * @since 1.0
 */
class Answer extends ActiveRecord
{

    /**
     * Markdown processed content
     * @var string
     */
    public $body;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%question_answer}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_AFTER_FIND => 'body'
                ],
                'value' => function ($event) {
                    return HtmlPurifier::process($event->sender->content);
                }
            ],
            [
                'class' => BlameableBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'user_id',
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['content'], 'required'],
            [['question_id'], 'exist', 'targetClass' => Question::className(), 'targetAttribute' => 'id']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('question', 'ID'),
            'content' => Yii::t('question', 'Content'),
            'status' => Yii::t('question', 'Status'),
        ];
    }

    /**
     * User Relation
     * @return \yii\db\ActiveQueryInterface
     */
    public function getUser()
    {
        return $this->hasOne(Yii::$app->user->identityClass, ['id' => 'user_id']);
    }

    /**
     * @return Question
     */
    public function getQuestion()
    {
        return $this->hasOne(Question::className(), ['id' => 'question_id']);
    }

    /**
     * 是否是作者
     * @return bool
     */
    public function isAuthor()
    {
        return $this->user_id == Yii::$app->user->id;
    }

    /**
     * Apply possible answers order to query
     * @param ActiveQuery $query
     * @param $order
     * @return string
     */
    public static function applyOrder(ActiveQuery $query, $order)
    {
        switch ($order) {
            case 'active':
                $query->orderBy('created_at ASC');
                break;

            case 'supports':
            default:
                $query->orderBy(['supports' => SORT_DESC]);
                break;
        }

        return $order;
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert) {
            /* 问题回答数+1 */
            $this->question->updateCounters(['answers' => 1]);
            /* 用户回答数+1 */
            Yii::$app->user->identity->userData->updateCounters(['answers' => 1]);

            /*记录动态*/
            Yii::$app->getModule('user')->doing($this->user_id, 'answer', get_class($this->question), $this->question->id, $this->question->title, $this->content);

            /*记录通知*/
            Yii::$app->getModule('user')->notify($this->user_id, $this->question->user_id, 'answer', $this->question->title, $this->question->id, $this->content);
        }
    }

    /**
     * This is invoked after the record is deleted.
     */
    public function afterDelete()
    {
        parent::afterDelete();
        $this->question->updateCounters(['answers' => -1]);
        /* 用户回答数-1 */
        Yii::$app->user->identity->userData->updateCounters(['answers' => -1]);
    }
}
