<?php
/**
 *	微信公众平台简单通讯录
 *  @author  Tarantula-7
 *  @link 1109197209@qq.com 
 *  @version 3.0
 *  FunctionBefore: 完成组创建、添加组内联系人、组内查询、组浏览、反馈(反馈信息存在数据库)
 *  FunctionAdded: 修改组内联系人
 */

define("TOKEN", "weixin");

$wechatObj = new wechatCallbackapiTest();

/*----------------------- From/ToUserNames -----------------------*/
$fromUserName = "";
$toUserName = "";

/*----------------------- 订阅推送信息 -----------------------*/
$welcomeMsg = "感谢您关注本公众号，Tarantula-7在这里提前祝您羊年大吉大利！\n"
			. "本站点基于微信平台，搭建在SAE云上，安全可靠；\n"
			. "致力于快速简便的联系人查询，不浪费您的存储，更无需担心忘记备份！\n";

$helpInfo = "回复组名快速登陆\n"
		  . "回复数字[1]开启登陆\n"
		  . "回复数字[2]创建组\n"
		  . "回复数字[3]反馈\n"
		  . "回复help获取帮助";
		   
$functionInfo = "回复关键字直接查询\n"
			  . "回复数字[1]添加组内联系人\n"
			  . "回复数字[2]创建新组\n"
			  . "回复数字[3]反馈\n"
			  . "回复数字[4]浏览组内所有联系人\n"
			  . "回复数字[5]修改组内联系人信息\n"
			  . "回复数字[6]退出当前组\n"
			  . "回复help获取帮助";
static $createPrompt = "请输入您要创建的组名(暂仅支持中文及字母)...\n(回复[q]/[quit]退出修改)";
static $addPrompt = "请根据提示输入联系人信息\n(回复[q]/[quit]退出本次添加)\n";
static $feedbackPrompt = "感谢您的鼎力支持,发送反馈信息或者截图异常信息给我们进行反馈...\n(回复[q]/[quit]退出本次添加)";
static $feedbackInfo1 = "反馈成功...\n谢谢您的耐心反馈，Tarantula-7会尽快修正您提出的问题...\n";
static $feedbackInfo2 = "反馈取消...\n如遇到什么问题,给您带来不便,欢迎您的耐心反馈...\n";

static $updatePrompt = "请输入您要修改的联系人姓名...\n(回复[q]/[quit]退出修改)";
$updateInfo = "回复数字[1]更改联系人电话\n"
		    . "回复数字[2]更改联系人短号\n"
			. "回复数字[3]更改联系人地址\n"
			. "回复数字[4]更改联系人邮箱";

/*----------------------- 错误提示信息 -----------------------*/		   
static $errInfo = "Invalid code...";
static $errGroupname = "暂不支持纯数字组名,请输入新组名...";

			
if (!isset($_GET['echostr'])) {
    $wechatObj->responseMsg();
}else{
    $wechatObj->valid();
}

class wechatCallbackapiTest
{
	// 创建公众号验证处理函数
    public function valid() 
    {
        $echoStr = $_GET["echostr"];
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if($tmpStr == $signature){
            echo $echoStr;
            exit;
        }
    }

    // 处理并回复用户发送过来的消息
    public function responseMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

        if (!empty($postStr)){
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			
			$GLOBALS["fromUserName"] = $postObj->ToUserName;
			$GLOBALS["toUserName"] = $postObj->FromUserName;
			
            // 把用户post过来的微信名当做sessionid
            session_id($GLOBALS["toUserName"]);
            session_start();

            $RX_TYPE = trim($postObj->MsgType);

            $result = "";
            switch ($RX_TYPE)
            {
                case "event":
                    $result = $this->receiveEvent($postObj->Event);
                    break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
                default:
                    $result = $this->transmitText("Unknow msg type: ".$RX_TYPE);
                    break;
            }
			echo $result;
        }
		else {
			echo "";
			exit;
        }
    }
	
// 处理各种类型信息函数 --------------------------------------------------------
	/* 	功能: 事件处理
		参数: 事件类型
	 */
    private function receiveEvent($event)
    {
        switch ($event)
        {
            // 关注事件
            case "subscribe":
                $content = $GLOBALS['welcomeMsg']."\n*Tarantula-7小助手提醒您:\n".$GLOBALS["helpInfo"];
                break;
            // 取消关注事件
            case "unsubscribe":
                break; 
            // 菜单点击事件
            case "CLICK":
                break; 
        }
        $result = $this->transmitText($content);
		
        return $result;
    }

    /* 功能: 文本处理
	   参数: 接收到的XML结构对象
	 */ 
    private function receiveText($object)
    {
        $content = trim($object->Content);
		
		// 回复help获取使用指南
		if("help" == $content){
			if($_SESSION['logined']){
				return $this->transmitText("*Tarantula-7服务小助手...\n".$GLOBALS["functionInfo"]);
			}
			else{
				return $this->transmitText("*Tarantula-7服务小助手...\n".$GLOBALS["helpInfo"]);
			}
		}
		
		// 反馈信息处理检测
		if(isset($_SESSION['feedbacking'])){
			return $this->handleFeedBack($content);
		}
		// 更新信息响应
		if($_SESSION["updating"]){
			if(preg_match("/^[0-9]{1,3}$/", $content)){
				switch($content){
					case "1":
						$_SESSION["updatingstate"] = 1;
						return $this->transmitText("请输入更新电话...\n(回复[q]/[quit]退出修改)");
						break;
					case "2":
						$_SESSION["updatingstate"] = 2;
						return $this->transmitText("请输入更新短号...\n(回复[q]/[quit]退出修改)");
						break;
					case "3":
						$_SESSION["updatingstate"] = 3;
						return $this->transmitText("请输入更新地址...\n(回复[q]/[quit]退出修改)");
						break;
					case "4":
						$_SESSION["updatingstate"] = 4;
						return $this->transmitText("请输入更新邮箱...\n(回复[q]/[quit]退出修改)");
						break;
					case "5":
						/* 进行更新 */
						return $this->contactUpdate();
						break;
					default:
						$_SESSION["updatingstate"] = 0;
						return $this->transmitText(($this->showUpdateInfo()).$GLOBALS["updateInfo"]);
						break;
				}
			}
			else{
				if($content == "q"||$content=="quit"){
					$this->updateFlagsClear();
					return $this->transmitText("成功取消,联系人修改失败...\n".$GLOBALS['functionInfo']);
				}
				else{
					switch($_SESSION["updatingstate"]){
						case 0:
							return $this->initUpdate($content);
							break;
						case 1:
							$_SESSION["updatingPhone"] = $content;
							return $this->transmitText(($this->showUpdateInfo())."\n" . $GLOBALS["updateInfo"]);
							break;
						case 2:
							$_SESSION["updatingMobilePhone"] = $content;
							return $this->transmitText(($this->showUpdateInfo())."\n" . $GLOBALS["updateInfo"]);
							break;
						case 3:
							$_SESSION["updatingAddress"] = $content;
							return $this->transmitText(($this->showUpdateInfo())."\n" . $GLOBALS["updateInfo"]);
							break;
						case 4:
							$_SESSION["updatingEmail"] = $content;
							return $this->transmitText(($this->showUpdateInfo())."\n" . $GLOBALS["updateInfo"]);
							break;
						default:
							return $this->transmitText("Tarantula-7不能处理您的问题...\n" . $GLOBALS["updateInfo"]);
							break;
					}
				}
			}
			
		}
		
		// 添加组内联系人检测
		if(isset($_SESSION['adding_state'])){
			if($content == "q"||$content=="quit"){
				$_SESSION['adding_state'] = null;
				return $this->transmitText("成功取消,联系人添加失败...\n".$GLOBALS['functionInfo']);
			}
			$result = "";
			switch($_SESSION['adding_state']){
				case 1:
					$_SESSION['name'] = $content;
					$_SESSION['adding_state'] = 2;
					$result = $this->transmitText($GLOBALS['addPrompt']."请输入联系人手机号码...");
					break;
				case 2:
					$_SESSION['phone'] = $content;
					$_SESSION['adding_state'] = 3;
					$result = $this->transmitText($GLOBALS['addPrompt']."请输入联系人短号...");
					break;
				case 3:
					$_SESSION['mobile'] = $content;
					$_SESSION['adding_state'] = 4;
					$result = $this->transmitText($GLOBALS['addPrompt']."请输入联系人地址...");
					break;
				case 4:
					$_SESSION['address'] = $content;
					$_SESSION['adding_state'] = 5;
					$result = $this->transmitText($GLOBALS['addPrompt']."请输入联系人邮箱...");
					break;
				case 5:
					$result = $this->contactAdd($_SESSION['name'], $_SESSION['phone'], $_SESSION['mobile'], $_SESSION['address'], $content);
					break;
			}
			return $result;
		}
		
		/* 创建组退出 */
		if($_SESSION['unloginedcreating']){
			if(("q"==$content)||("quit"==$content)){
				$_SESSION['unloginedcreating'] = null;
				return $this->transmitText("取消创建...\n".$GLOBALS["helpInfo"]);
			}
		}
		if($_SESSION['loginedcreating']){
			if(("q"==$content)||("quit"==$content)){
				$_SESSION['loginedcreating'] = null;
				return $this->transmitText("取消创建...\n".$GLOBALS["functionInfo"]);
			}
		}
		
		// 回复指定数字...
		if(preg_match("/^[0-9]{1,2}$/", $content))
		{
			// has logined
			if($_SESSION['logined']){
				// 登陆状态下的创建 非法输入的组名 ==> 不允许创建并提示使用指南
				if($_SESSION['loginedcreating']){
					$_SESSION['loginedcreating'] = null;
					return $this->transmitText($GLOBALS['errInfo']."\n".$GLOBALS['functionInfo']);
				}
				
				switch ($content)
				{
					case "1":
						$result = $this->transmitText($GLOBALS['addPrompt']."请输入联系人姓名...");
						$_SESSION['adding_state'] = 1;
						$_SESSION["waitingfor5"] = null;
						break;
					case "2":
						$result = $this->handleCreate($content);
						$_SESSION["waitingfor5"] = null;
						break;
					case "3":
						$result = $this->handleFeedBack($content);
						$_SESSION["waitingfor5"] = null;
						break;
					case "4":
						$_SESSION["waitingfor5"] = null;
						/* 函数中waitingfor5标记可能被修改 */
						$result = $this->contactList();
						break;
					case "5":
						if($_SESSION["waitingfor5"]){
							$_SESSION["updating"] = true;
							$_SESSION["updatingstate"] = 0;
							$result = $this->initUpdate($_SESSION["updatingName"]);
							$_SESSION["waitingfor5"] = null;
						}
						else{
							$result = $this->transmitText($GLOBALS["updatePrompt"]);
							$_SESSION["updating"] = true;
							$_SESSION["updatingstate"] = 0;
						}
						break;
					case "6":
						$result = $this->transmitText("成功退出...\n".$GLOBALS['helpInfo']);
						session_destroy();
						break;
					default:
						$result = $this->transmitText("未定义数字...\n".$GLOBALS['functionInfo']);
						break;
				}
			}
			// never logined
			else{
				switch ($content)
				{
					case "1":
						$result = $this->handleLogin($content);
						/* 清除2遗留标记, 避免2->1或者2->3问题 */
						$_SESSION["unloginedcreating"] = null;
						$_SESSION["waitingfor2"] = null;
						break;
					case "2":
						$result = $this->handleCreate($content);
						break;
					case "3":
						$result = $this->handleFeedBack($content);
						$_SESSION["unloginedcreating"] = null;
						$_SESSION["waitingfor2"] = null;
						break;
					default:
						$result = $this->transmitText("未定义数字...\n".$GLOBALS['helpInfo']);
						session_destroy();
						break;
				}
			}
		} 
		// 回复指定数字之外的子串
		else
		{
			// 登陆即直接查询联系人
			if($_SESSION['logined'] && !$_SESSION['loginedcreating']){
				$result = $this->contactQuery($content);
			}
			// 为登陆状态下创建组输入的组名
			elseif($_SESSION['logined'] && $_SESSION['loginedcreating']){
				$result = $this->handleCreate($content);
			}
			// 非法输入的组名
			elseif($_SESSION['waitingfor2']){
				$result = $this->transmitText($GLOBALS['errInfo']."\n".$GLOBALS['helpInfo']);
				$_SESSION['waitingfor2'] = null;
			}
			// 为非登录状态下创建组输入的组名
			elseif($_SESSION['unloginedcreating']){
				$result = $this->handleCreate($content);
			}
			// 进行登陆检测...
			else{
				$result = $this->handleLogin($content);
			}
		}
        return $result;
    }
	
	/* 	功能: 处理用户登录组
		参数: 选项1/组名
	 */
	private function handleLogin($content)
	{
		if($content == "1")
		{
			$result = $this->transmitText("请输入组名进行登陆...");
			session_destroy();
		}
		else
		{
			if($this->groupExists($content)){
				$txt = "";
				if($_SESSION['loginedcreating'] || $_SESSION['unloginedcreating'] || $_SESSION['waitingfor2']){
					$txt = "创建并";
					/* 清除标记 */
					$_SESSION['loginedcreating'] = null;
					$_SESSION['unloginedcreating'] = null;
					$_SESSION['waitingfor2'] = null;
				}
				$result = $this->transmitText("成功".$txt."登陆组【'$content'】\n".$GLOBALS['functionInfo']);
				
				/* 设置标记 */
				$_SESSION['logined'] = true;
				$_SESSION['group'] = $content;
			}
			else{
				$result = $this->transmitText("该组不存在...\n回复数字[2]创建组【".$content."】");
				$_SESSION['waitingfor2'] = true;
				$_SESSION['group'] = $content;
			}	
		}
        return $result;
	}
	
	/* 	功能: 处理用户创建组
		参数: 组名/选项2
	 */
	private function handleCreate($content)
	{
		if("2" == $content){
			/* 回复2确认创建该组 */
			if($_SESSION["waitingfor2"]){
				return $this->groupCreate($_SESSION["group"]);
			}
			/* 选项2开启创建 */
			if($_SESSION["logined"]){
				$_SESSION['loginedcreating'] = true;
			}
			else{
				$_SESSION['unloginedcreating'] = true;
			}
			return $this->transmitText($GLOBALS['createPrompt']);
		}
		else{
			return $this->groupCreate($content);
		}
	}
	
	/* 	功能: 处理用户创建组
		参数: 组名/2
	 */
	public function groupCreate($groupname)
	{
		// 禁止纯数字组名
		if(preg_match("/^[0-9]{1,}$/", $groupname)){
			return $this->transmitText($GLOBALS['errGroupname']);
		}
		
		if($this->groupExists($groupname)){
			$result = $this->transmitText("组名已存在,请输入新组名...");
		}
		else{
			/* 创建组并添加到系统Groups */
			$mylink = $this->sqlOpen();
			$time = date('Y-m-d');
			$creator = $GLOBALS['toUserName'];
			$sql = "insert into Groups values('$groupname', '$creator', '$time');";
			mysql_query($sql, $mylink);
			$this->sqlClose();
			// 创建组后要创建对应的组表
			$this->sqlCreateGroupTable($groupname);
			/* 创建后登陆 */
			$result = $this->handleLogin($groupname);
			
			/* 清除标记 */
			$_SESSION['unloginedcreating'] = null;
			$_SESSION['loginedcreating'] = null;
		}
        return $result;
	}
	
	/* 	功能: 处理用户反馈
		参数:
	 */
	private function handleFeedBack($content)
	{
		if($content == "3" && (!$_SESSION['feedbacking'])){
			$result = $this->transmitText($GLOBALS["feedbackPrompt"]);
			$_SESSION['feedbacking'] = true;
		}
		else{
			if($content=="q"|| $content=="quit"){
				$_SESSION['feedbacking'] = null;
				if($_SESSION['logined']){
					return $this->transmitText($GLOBALS["feedbackInfo2"].$GLOBALS["functionInfo"]);
				}
				else{
					return $this->transmitText($GLOBALS["feedbackInfo2"].$GLOBALS["helpInfo"]);
				}
			}
			if(!($this->feedBack($content))){
				return $this->transmitText("反馈信息不合法,请重新输入...\n(回复[q]/[quit]退出本次添加)");
			}
			$_SESSION['feedbacking'] = null;
			if($_SESSION['logined']){
				$result = $this->transmitText($GLOBALS["feedbackInfo1"].$GLOBALS["functionInfo"]);
			}
			else{
				$result = $this->transmitText($GLOBALS["feedbackInfo1"].$GLOBALS["helpInfo"]);
			}
		}
        return $result;
	}
	
	/* 	功能: 用户反馈处理
		参数: 反馈信息
	 */
	private function feedBack($info)
	{
		$feedbacker = $GLOBALS["toUserName"];
		$feedbacktime = date("Y-m-d");
		$mylink = $this->sqlOpen();
		$status = 0;
		if($this->emailSend($info)) $status=1;
		$sql = "insert into FeedBack values('$feedbacker', '$info', '$feedbacktime', '$status');";
		$feedbackerr = mysql_query($sql, $mylink);
		$this->sqlClose($mylink);
		return $feedbackerr;
	}
	
	/* 	功能: 用户反馈邮箱提醒
		参数: 反馈信息
	 */
	private function emailSend($content)
	{
		$mail = new SaeMail();
		$mail->clean(); 
		$result = $mail->quickSend( '1109197209@qq.com' ,
				 '微信公众号反馈信息' , '反馈信息: '.$content , 
				 '1109197209@qq.com' , 'chenshj35' , 'smtp.qq.com' , 25 ); 

		return $result;
	}
	
	/* 	功能: 检测组是否存在
		参数: 用户输入的组名
	 */
	private function groupExists($groupname)
	{
		$mylink = $this->sqlOpen();
		$sql = "select * from Groups where GroupName='$groupname' limit 1;";
		$result = mysql_query($sql, $mylink);
		$this->sqlClose($mylink);
		return mysql_num_rows($result)>=1;
	}

	public function sqlOpen()
	{
		$mylink = mysql_connect(SAE_MYSQL_HOST_M.':'.SAE_MYSQL_PORT, SAE_MYSQL_USER, SAE_MYSQL_PASS) or die("Error connecting to database.");
		mysql_select_db(SAE_MYSQL_DB, $mylink) or die("Couldn't select the database.");
		return $mylink;
	}
	
	public function sqlClose($mylink)
	{
		mysql_close($mylink);
	}
	
	/* 	功能: 创建组表
		参数: 表名<==>组名
		create table groupname(
		Name varchar(18), 
		Phone varchar(16), 
		MobilePhone varchar(19), 
		Address varchar(10), 
		Email varchar(10),
		LastUpdateTime date);
	 */
	private function sqlCreateGroupTable($groupname)
	{
		$tableCreate = "create table %s(
						Name varchar(20), 
						Phone varchar(16), 
						MobilePhone varchar(19), 
						Address varchar(20), 
						Email varchar(20),
						LastUpdateTime date);";
		$sql = sprintf($tableCreate, $groupname);
		$mylink = $this->sqlOpen();
		mysql_query($sql, $mylink);
		$this->sqlClose($mylink);
	}
	
	/* 	功能: 处理数据库插入操作
		参数: 表名
			  单字段数据
	 */
	private function sqlInsert_1($table, $datas)
	{
		$mylink = mysql_connect(SAE_MYSQL_HOST_M.':'.SAE_MYSQL_PORT, SAE_MYSQL_USER, SAE_MYSQL_PASS) or die("Error connecting to database.");
		mysql_select_db(SAE_MYSQL_DB, $mylink) or die("Couldn't select the database.");

		//TODO 此处可以进行优化 只操作一次
		foreach($datas as $data)
		{
			$sql = "insert into '$table' values('$data');";
			mysql_query($sql, $mylink);
		}
		
		mysql_close($mylink);
	}
	
	/* 	功能: 处理数据库插入操作
		参数: 表名
			  查询where条件子串
	 */
	private function sqlQuery($table, $condition)
	{
		$mylink = mysql_connect(SAE_MYSQL_HOST_M.':'.SAE_MYSQL_PORT, SAE_MYSQL_USER, SAE_MYSQL_PASS) or die("Error connecting to database.");
		mysql_select_db(SAE_MYSQL_DB, $mylink) or die("Couldn't select the database.");

		$sql = "select * from '$table' where '$condition';";		
		$result = mysql_query($sql, $mylink);
		
		mysql_close($mylink);
		return mysql_fetch_array($result);
	}
	
	/* 功能: 添加联系人
	   参数: 查询联系人名字
	 */ 
	private function contactAdd($name, $phone, $mobile, $address, $email)
	{
		$tableInsert = "insert into %s values(
						'%s', 
						'%s', 
						'%s', 
						'%s', 
						'%s',
						'%s');";
		$sql = sprintf($tableInsert, $_SESSION['group'], $name, $phone, $mobile, $address, $email, date("Y-m-d"));
		$mylink = $this->sqlOpen();
		mysql_query($sql, $mylink);
		$this->sqlClose($mylink);
		$_SESSION['adding_state'] = null;
		return $this->transmitText("成功添加联系人[$name]...\n".$GLOBALS["functionInfo"]);
	}
	
	/* 功能: 浏览联系人
	   参数: 查询联系人名字
	 */ 
	private function contactList()
	{
		return $this->contactQuery(null);
	}
	
	/* 功能: 联系方式查询
	   参数: 搜索联系人方式的名字关键字
	 */ 
	private function contactQuery($name)
	{
		$group = $_SESSION['group'];
		$contactname = "";
		$sql = "";
		$content = "";
		$listflag = is_null($name);
		if($listflag){
			$sql = "select * from $group order by Name limit 30;";
		}
		else{
			$sql = "select * from $group where Name like '%$name%' order by Name;";
		}	
        
		$mylink = $this->sqlOpen();
        $result = mysql_query($sql, $mylink);
		$this->sqlClose($mylink);
		$findnum = mysql_num_rows($result); 
        /* 数据库查询 */
        if($findnum <= 0)
        {
            $content = ($listflag)? "组".$group."内暂无联系人,回复数字[1]开始添加"
					: "搜索不到联系人[$name]或缩简关键值,重新搜索...";
        }
        else
        {
			$content = "*共搜索到".$findnum."位联系人*\n";
            while($row = mysql_fetch_array($result))
            {
				$contactname = $row['Name'];
                $content .= $contactname. "...:\n";
				$content .= "|--Phone: ".$row['Phone']."\n";
				$content .= "|--短号: ".$row['MobilePhone']."\n";
				$content .= "|--Address: ".$row['Address']."\n";
				$content .= "|--Email:\n        ".$row['Email']."\n";
                $content .=  "[Updated @".$row['LastUpdateTime']."]\n\n";
            }
			if($findnum == 1){
				if($this->contactExists($contactname)){
					$content .= "(回复数字[5]修改该联系人信息)\n";
					$_SESSION["waitingfor5"] = true;
					$_SESSION["updatingName"] = $contactname;
				}
			}
            $content .= "?搜索不到联系人请精简关键值,重新搜索?";
        }
        return $this->transmitText($content);
	}
	
	/* 功能: 处理联系方式更新
	   参数: 搜索联系人方式的名字关键字
	 */
	private function initUpdate($contactName)
	{
		if($this->contactExists($contactName)){
			$info = $this->showUpdateInfo();
			return $this->transmitText($info.$GLOBALS["updateInfo"]);
		}
		else{
			return $this->transmitText("不存在联系人[$contactName]\n".$GLOBALS["updatePrompt"]);
		}
	}
	
	/* 清除更新标记 */
	private function updateFlagsClear()
	{
		$_SESSION["updating"]=null;
		$_SESSION["updatingstate"]=null;
		$_SESSION["waitingfor5"]=null;
		$_SESSION["updatingPhone"]=null;
		$_SESSION["updatingMobilePhone"]=null;
		$_SESSION["updatingAddress"]=null;
		$_SESSION["updatingEmail"]=null;
	}
	
	/* 功能: 显示当前更新信息
	   参数: 
	 */
	private function showUpdateInfo()
	{
		$content = $_SESSION['updatingName']. "...:\n";
		$content .= "|--Phone: ".$_SESSION['updatingPhone']."\n";
		$content .= "|--短号: ".$_SESSION['updatingMobilePhone']."\n";
		$content .= "|--Address: ".$_SESSION['updatingAddress']."\n";
		$content .= "|--Email:\n        ".$_SESSION['updatingEmail']."\n\n";
		$content .= "(回复[q]/[quit]退出修改,回复数字[5]确认修改)\n";
		return $content;
	}
	
	/* 功能: 修改联系方式
	   参数: void
	 */ 
	private function contactUpdate()
	{
		$sqlTpl = "update %s set Phone='%s', MobilePhone='%s', "
			 . "Address='%s', Email='%s', LastUpdateTime='%s' where Name='%s';";
		$sql = sprintf($sqlTpl, $_SESSION["group"], $_SESSION["updatingPhone"],
				$_SESSION["updatingMobilePhone"], $_SESSION["updatingAddress"], 
				$_SESSION["updatingEmail"], date("Y-m-d"), $_SESSION["updatingName"]);
		$mylink = $this->sqlOpen();
		if(mysql_Query($sql, $mylink)){
			$result = $this->transmitText("成功修改...\n".$GLOBALS["functionInfo"]);
		}
		else{
			$result = $this->transmitText("修改失败,未知错误...\n".$GLOBALS["functionInfo"]);
		}
		$this->sqlClose($mylink);
		/* 清除标记 */
		$this->updateFlagsClear();
		
		return $result;
	}
	
	/* 功能: 修改联系人是否存在组内检测
	   参数: 联系人名字
	 */ 
	private function contactExists($contactName)
	{
		$group = $_SESSION["group"];
		$sql = "select * from $group where Name='$contactName' limit 1;";
		$mylink = $this->sqlOpen();
		$result = mysql_Query($sql, $mylink);
		$this->sqlClose($mylink);
		if(mysql_num_rows($result) >= 1){
			$row = mysql_fetch_array($result);
			$_SESSION["updatingName"] = $row["Name"];
			$_SESSION["updatingPhone"] = $row["Phone"];
			$_SESSION["updatingMobilePhone"] = $row["MobilePhone"];
			$_SESSION["updatingAddress"] = $row["Address"];
			$_SESSION["updatingEmail"] = $row["Email"];
			return true;
		}
		else{
			$_SESSION["updatingName"] = null;
			$_SESSION["updatingPhone"] = null;
			$_SESSION["updatingMobilePhone"] = null;
			$_SESSION["updatingAddress"] = null;
			$_SESSION["updatingEmail"] = null;
			return false;
		}
	}
	

// 推送各种类型信息函数 --------------------------------------------------------
	/* 	功能: 传输文本
		参数: 文本内容
	 */
    private function transmitText($content)
    {
        if (!isset($content) || empty($content)){
            return "";
        }

        $textTpl = 
        "<xml>
            <ToUserName><![CDATA[%s]]></ToUserName>
            <FromUserName><![CDATA[%s]]></FromUserName>
            <CreateTime>%s</CreateTime>
            <MsgType><![CDATA[text]]></MsgType>
            <Content><![CDATA[%s]]></Content>
        </xml>";
       
        $result = sprintf($textTpl, $GLOBALS["toUserName"], $GLOBALS["fromUserName"], time(), $content);
        return $result;
    }
}

?>