<?php

namespace app\models;

use app\components\Command;
use Yii;

/**
 * This is the model class for table "group".
 *
 * @property integer $id
 * @property integer $project_id
 * @property string $user_id
 */
class Group extends \yii\db\ActiveRecord
{
    /**
     * 普通开发者
     */
    const TYPE_USER = 0;

    /**
     * 管理员
     */
    const TYPE_ADMIN = 1;

    /**
     * 测试人员
     */
    const TYPE_TESTER = 2;

    /**
     * 运维人员
     */
    const TYPE_OPERATIONS = 4;


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'group';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['project_id', 'user_id'], 'required'],
            [['project_id', 'user_id', 'type'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'project_id' => 'Project ID',
            'user_id' => 'User ID',
            'type' => 'Type',
        ];
    }

    /**
     * width('user')
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * 项目添加用户
     *
     * @param $projectId
     * @param $userId array
     * @return bool
     */
    public static function addGroupUser($projectId, $userIds, $type = Group::TYPE_USER)
    {
        // 是否已在组内
        $exitsUids = Group::find()
            ->select(['user_id'])
            ->where(['project_id' => $projectId, 'user_id' => $userIds])
            ->column();
        $notExists = array_diff($userIds, $exitsUids);
        if (empty($notExists)) return true;

        $group = new Group();
        foreach ($notExists as $uid) {
            $relation = clone $group;
            $relation->attributes = [
                'project_id' => $projectId,
                'user_id' => $uid,
                'type' => $type,
            ];
            $relation->save();
        }
        return true;
    }

    /**
     * 是否为该项目的审核管理员
     *
     * @param $projectId
     * @param $uid
     * @return int|string
     */
    public static function isAuditAdmin($uid, $projectId)
    {

        $user = User::findIdentity($uid);

        return $user->role == User::ROLE_ADMIN;
        /*$type = static::find()
            ->select(['type'])
            ->where(['user_id' => $uid, 'project_id' => $projectId])
            ->column();

//        Command::log('uid:' . $uid . ' projectId:' . $projectId . ' type:' . $type[0] . (self::isAdmin($type[0]) ? ' admin' : ' not admin'));

        return self::isAdmin($type[0]);*/
    }

    /**
     * 获取用户可以审核的项目
     *
     * @param $uid
     * @return array
     */
    public static function getAuditProjectIds($uid)
    {
        /*return static::find()
            ->select(['project_id'])
            ->where(['and', 'user_id=:uid', ['in', 'type', self::getAdminTypes()]], [':uid' => $uid])
            ->column();*/

        return static::find()
            ->select(['project_id'])
            ->where(['and', 'user_id=:uid', 'type>:type'], [':uid' => $uid, ':type' => 0])
            ->column();
    }

    public static function getAdminTypeIds($project_id, $adminType = self::TYPE_ADMIN)
    {
        return static::find()
            ->select(['user_id'])
            ->where(['and', 'project_id=:pid', ['in', 'type', self::getAdminTypes($adminType)]], [':pid' => $project_id])
            ->column();
    }

    public static function getUserProject($uid)
    {
        $projects = static::find()->where(['user_id' => $uid])->asArray()->all();
        $arrays = [];
        foreach ($projects as $project) {
            $arrays[$project['project_id']] = $project['type'];
        }
        return $arrays;
    }

    public static function isAdmin($type)
    {
        return $type & self::TYPE_ADMIN;
    }

    public static function isTester($type)
    {
        return $type & self::TYPE_TESTER;
    }

    public static function isOperation($type)
    {
        return $type & self::TYPE_OPERATIONS;
    }

    public static function getOppositeAdmin($type)
    {
        return self::isAdmin($type) ? ($type - self::TYPE_ADMIN) : ($type + self::TYPE_ADMIN);
    }

    public static function getOppositeTester($type)
    {
        return self::isTester($type) ? ($type - self::TYPE_TESTER) : ($type + self::TYPE_TESTER);
    }

    public static function getOppositeOperations($type)
    {
        return self::isOperation($type) ? ($type - self::TYPE_OPERATIONS) : ($type + self::TYPE_OPERATIONS);
    }

    public static function getAdminTypes($adminType = self::TYPE_ADMIN)
    {
        if ($adminType == self::TYPE_ADMIN) {
            return [1, 3, 5, 7];
        } else if ($adminType == self::TYPE_TESTER) {
            return [2, 3, 6, 7];
        } else if ($adminType == self::TYPE_OPERATIONS) {
            return [4, 5, 6, 7];
        }
    }

}
