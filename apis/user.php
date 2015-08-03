<?php

class User
{
	/**
	 * 用户登陆
	 */
	public function login(&$params, &$res)
	{
        $time = time();
        $nowDate = $time - ($time % 86400);
        $openId = mysql_escape_string($params->id);

        $this->checkAddPower($openId, $nowDate);

        $name = mysql_escape_string($params->name);
        $pic = mysql_escape_string($params->pic);
        $sql = "INSERT INTO tbl_user(id,name,pic,last_login_utc) VALUES('%s','%s','%s',%d) ON DUPLICATE KEY UPDATE name='%s',pic='%s',last_login_utc=%d";
        $sql = sprintf($sql, $openId, $name, $pic, $nowDate, $name, $pic, $nowDate);

		$st = new SqlHelper();
		$st->conn();
		$result = $st->modify($sql);
        $st->close();

		if($result)
		{
			//数据登陆成功，开始获取用户数据
            $data = $this->getUserInfo($openId);
            $data['receives'] = $this->getReceives($openId);
            $data['rewards'] = $this->getRewards($openId);
            $data['user_amount'] = $this->getUserAmount();
            $res['data'] = $data;
		}
		else
		{
            $res['error'] = 1;
		}
	}

    /**
     * 获取用户总数
     */
    private function getUserAmount()
    {
        $sql = "SELECT COUNT(*) AS count FROM tbl_user";
        $st = new SqlHelper();
        $st->conn();
        $result = $st->query($sql);
        $st->close();
        if($result)
        {
            return $result[0]['count'];
        }
        return 485;
    }

    /**
     * 给用户增加体力
     * @param $id
     * @param $nowDate
     */
    private function checkAddPower($id, $nowDate)
    {
        $sql = "UPDATE tbl_user SET power=power+5 WHERE last_login_utc!=%d AND id='%s'";
        $sql = sprintf($sql, $nowDate, $id);
        $st = new SqlHelper();
        $st->conn();
        $st->modify($sql);
        $st->close();
    }

    /**
     * 获取用户信息
     * @param $params
     * @param $res
     */
    public function get_info(&$params, &$res)
    {
        $id = mysql_escape_string($params->id);
        $data = $this->getUserInfo($id);
        $data['receives'] = $this->getReceives($id);
        $data['rewards'] = $this->getRewards($id);
        $res['data'] = $data;
    }

    /**
     * 获取用户信息
     * @param $id
     */
    private function getUserInfo($id)
    {
        $sql = "SELECT * FROM tbl_user WHERE id='%s'";
        $sql = sprintf($sql, mysql_escape_string($id));
        $st = new SqlHelper();
        $st->conn();
        $result = $st->query($sql);
        $result = $result[0];
        $st->close();
        return $result;
    }

    /**
     * 收到的助力信息
     * @param $id
     * @return array|bool
     */
    private function getReceives($id)
    {
        $sql = "SELECT id,name,pic FROM tbl_share_record AS t1 LEFT JOIN tbl_user AS t2 ON t1.sender_id = t2.id WHERE receiver_id = '%s'";
        $sql = sprintf($sql, $id);
        //die($sql);
        $st = new SqlHelper();
        $st->conn();
        $result = $st->query($sql);
        $st->close();
        return $result;
    }

    /**
     * 收到的奖励信息
     * @param $id
     * @return array|bool
     */
    function getRewards($id)
    {
        $sql = "SELECT `key`,type,used_utc FROM tbl_key WHERE user_id='%s';";
        $sql = sprintf($sql, mysql_escape_string($id));
        $st = new SqlHelper();
        $st->conn();
        $result = $st->query($sql);
        $st->close();
        return $result;
    }


    /**
     * 给好友助力
     * @param $params
     * @param $res
     */
    public function give_power(&$params, &$res)
    {
        $id = $params->id;
        $target_id = $params->target_id;

        $sql = "INSERT INTO tbl_share_record(sender_id,receiver_id,time,time_utc) VALUES('%s','%s',CURTIME(),%d)";
        $sql = sprintf($sql, mysql_escape_string($id), mysql_escape_string($target_id), time());
        //die($sql);
        $st = new SqlHelper();
        $st->conn();
        $result = $st->modify($sql);
        $st->close();

        $data = array();
        if(false == $result)
        {
            $data['success'] = 0;
        }
        else
        {
            $sql = "UPDATE tbl_user SET power=power+1 WHERE id='%s'";
            $sql = sprintf($sql, mysql_escape_string($target_id));
            $st->conn();
            $st->modify($sql);
            $st->close();
            $data['success'] = 1;
        }

        $res['data'] = $data;
    }

    /**
     * 发送抽奖信息
     * @param $params
     * @param $res
     */
//    public function lottery(&$params, &$res)
//    {
//        $id = mysql_escape_string($params->id);
//        $phone  = mysql_escape_string($params->phone);
//        $name = mysql_escape_string($params->name);
//        $address = mysql_escape_string($params->address);
//
//
//    }

    /**
     * 提交游戏结果
     * @param $params
     * @param $res
     */
    public function game_result(&$params, &$res)
    {
        $id = mysql_escape_string($params->id);
        $score = intval($params->score);

        $user = $this->getUserInfo($id);
        if(intval($user['power']) <= 0)
        {
            $res['error'] = 1;
            return;
        }

        $sql = null;
        if(intval($user['best_score']) < $score)
        {
            //新纪录
            $sql = "UPDATE tbl_user SET total_score=total_score + %d,power=power-1,best_score=%d,best_score_utc=%d,best_score_time=NOW() WHERE id='%s';";
            $sql = sprintf($sql, $score, $score, time(), $id);
        }
        else
        {
            $sql = "UPDATE tbl_user SET total_score=total_score + %d,power=power-1 WHERE id='%s'";
            $sql = sprintf($sql, $score, $id);
        }

        $st = new SqlHelper();
        $st->conn();
        $result = $st->modify($sql);
        $st->close();
        if(true == $result)
        {
            $data = $this->lottery($id, $score);
            $res['data'] = $data;
        }
        else
        {
            $res['error'] = 2;
        }
    }

    /**
     * 为玩家进行一次抽奖
     * @param $id
     * @param $score
     */
    private function lottery($id, $score)
    {
        $level = 0;
        if($score >= 340)
        {
            $level = 340;
        }
        else if($score >= 200)
        {
            $level = 200;
        }
        else if($score >= 100)
        {
            $level = 100;
        }

        $reward = array();
        $reward['reward_key'] = 0;
        $reward['reward_type'] = 0;

        $code = rand(0,9);
        if($code <= 2)
        {
            $now = time();
            //可以中奖，这里随机出一个奖励type
            $sql = "UPDATE tbl_key SET user_id='%s',used_utc=%d WHERE level = %d AND user_id = '' ORDER BY RAND() LIMIT 1";
            $sql = sprintf($sql, $id, $now, $level);
            $st = new SqlHelper();
            $st->conn();
            $result = $st->modify($sql);
            $affectedRows = mysql_affected_rows();
            $st->close();

            if(true == $result &&  $affectedRows > 0)
            {
                $sql = "SELECT `key`,type FROM tbl_key WHERE user_id='%s' AND used_utc=%d";
                $sql = sprintf($sql, $id, $now);
                //die($sql);
                $st->conn();
                $result = $st->query($sql);
                $st->close();
                if(null != $result)
                {
                    $reward['reward_key'] = $result[0]['key'];
                    $reward['reward_type'] = $result[0]['type'];
                }
            }
        }
        return $reward;
    }

    /**
     * 获取排行榜
     * @param $params
     * @param $res
     */
    public function get_rank(&$params, &$res)
    {
        $st = new SqlHelper();
        $data = array();
        $data['self'] = 1;

        $id = mysql_escape_string($params->id);
        $sql="SELECT COUNT(*) AS position FROM tbl_user WHERE total_score > (SELECT total_score FROM tbl_user WHERE id = '%s')";
        $sql = sprintf($sql, $id);
        $st->conn();
        $result = $st->query($sql);
        $data['self'] = $result[0]['position'];
        $st->close();

        $sql="SELECT id,name,pic,total_score FROM tbl_user ORDER BY total_score DESC LIMIT 0,20";
        $st->conn();
        $result = $st->query($sql);
        $st->close();
        $data['list'] = $result;

        $res['data'] = $data;
        return $result;
    }
}