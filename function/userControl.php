<?php
if(!defined('IN_SKYOJSYSTEM'))
{
  exit('Access denied');
}

$permission = array();
$permission['guest']['uid'] = "0";

$_G = $permission['guest'];

class userControl
{
    //this must call before use $_G[uid]
    static function registertoken($namespace,$timeleft)
    {
        global $_G,$_E,$_config;
        $table = DB::tname('usertoken');
        $token = md5(uniqid($namespace,true));
        $timeout = time() + $timeleft;
        
        $_SESSION[$namespace][$token]['timeout'] = $timeout;
        $_SESSION[$namespace][$token]['uid'] = $_G['uid'];
        setcookie($_config['cookie']['namepre'].'_'.$namespace,$token,$timeout,$_E['SITEDIR']);
        if($_G['uid'])
        {
            $id = $_G['uid'];
            if(!DB::query("INSERT INTO `$table`".
                        "(`uid`, `timeout`, `type`, `token`)".
                        "VALUES ($id,$timeout,'$namespace','$token')"))
            {
                return false;
            }
        }
        return $token;
    }
    
    static function deletetoken($namespace)
    {
        global $_G,$_E,$_config;
        $table = DB::tname('usertoken');
        
        setcookie($_config['cookie']['namepre'].'_'.$namespace,'',0,$_E['SITEDIR']);
        if( isset( $_SESSION[$namespace] ) )
        {
            unset($_SESSION[$namespace]);
        }
        if($_G['uid'])
        {
            $id = $_G['uid'];
            DB::query("DELETE FROM  `$table` ".
                        " WHERE  `uid` = $id ".
                        " AND  `type` = '$namespace'");
        }
        $time = time();
        DB::query("DELETE FROM  `$table` ".
                    " WHERE  `timeout` < $time ");
    }
    #bool userControl::checktoken(namespace)
    #if function return true ,it mean two things:
    #1.$_COOKIE[$_config['cookie']['namepre'].'_uid'] is leagl
    #2.token $namespace is leagl
    static function checktoken($namespace)
    {
        global $_G,$_config;
        $table = DB::tname('usertoken');
        if( !isset($_COOKIE[$_config['cookie']['namepre'].'_'.$namespace]) || !isset($_COOKIE[$_config['cookie']['namepre'].'_uid']) )
        {
            return false;
        }
        
        $token = isset($_COOKIE[$_config['cookie']['namepre'].'_'.$namespace])?$_COOKIE[$_config['cookie']['namepre'].'_'.$namespace]:'';
        $uid   = isset($_COOKIE[$_config['cookie']['namepre'].'_uid'])?$_COOKIE[$_config['cookie']['namepre'].'_uid']:'';
        
        if( !preg_match('/^[a-z0-9]+$/',$token) ||
            !preg_match('/^[0-9]+$/',$uid))
        {
            return false;
        }
        
        if( isset($_SESSION[$namespace][$token]) )
        {
            if( $_SESSION[$namespace][$token]['uid'] == $uid )
                return true;
            else
                return false;
        }
        else{
            //Load form SQL
            if($sqlres = DB::query("SELECT * FROM  `$table` ".
                                   " WHERE  `uid` = $uid ".
                                   " AND  `token` ='$token'"))
            {
                if( $sqldata = DB::fetch($sqlres) )
                {
                    if( $sqldata['timeout']>time() )
                    {
                        return true;
                    }
                    else
                    {
                        DB::query("DELETE FROM  `$table` ".
                                     " WHERE  `uid` = $uid".
                                     " AND  `token` ='$token'");
                        return false;
                    }
                }
                else //No data stroe in MYSQL
                {
                    return false;
                }
            }
            else // SQL error
            {
                return false;
            }
        }
        //protect
        return false;
    }
    
    #userControl::intro()
    #this function must call first to check if user has logined and set var $_G
    static function intro()
    {
        global $_G,$permission,$_config;
        $acctable = DB::tname('account');
        if( userControl::checktoken('login') )
        {
            //load user data
            //$_COOKIE[$_config['cookie']['namepre'].'_uid'] is checked in userControl::checktoken
            $loginuid = $_COOKIE[$_config['cookie']['namepre'].'_uid'];
            if( $cache = DB::loadcache('login',$loginuid) )
            {
                //Load form cache
                $_G = $cache;
            }
            else
            {
                //reload from SQL
                $sqlres=DB::query("SELECT * FROM  `$acctable`".
                                    " WHERE  `uid` =  $loginuid");
                if( $sqldata = DB::fetch($sqlres) )
                {
                    $_G = $sqldata;
                    DB::putcache('login',$_G,5,$loginuid);
                }
                else //sql error
                {
                    #may be someone drop DB... It will lost the data of users.
                    $_G = $permission['guest'];
                }
            }
        }
        else // guest
        {
            $_G = $permission['guest'];
        }
    }
    
    static function SetLoginToken($uid)
    {
        global $_G,$_E,$_config;
        $acctable = DB::tname('account');
        
        $sqlres=DB::query("SELECT * FROM  `$acctable` ".
                            " WHERE  `uid` =  $uid ");
        if( $sqldata = DB::fetch($sqlres) )
        {
            $_G['uid'] = $uid;
            userControl::registertoken('login',864000);
            // save $sqldata in cache
            DB::putcache('login', $sqldata ,10 ,$uid);
            setcookie($_config['cookie']['namepre'].'_uid',$uid,time()+864000,$_E['SITEDIR']);
            return true;
        }
        else
        {
            return false;
        }
    }
    
    static function DelLoginToken()
    {
        global $_G;
        DB::deletecache('login',$_G['uid']);
        userControl::deletetoken('login');
    }
    
    static function getuserdata( $table ,$uid = null )
    {
        $table = DB::tname($table);
    }
    
    static function getpermission($uid)
    {
        global $_G,$_E,$_config;
        if( $uid == -1 )
            return false;
        if( $uid == $_G['uid'])
            return true;
        if(in_array($_G['uid'],$_E['site']['admin']))
            return true;
        return false;
    }
    static function isAdmin( $uid = null )
    {
        global $_G,$_E,$_config;
        if($uid === null)
            return in_array($_G['uid'],$_E['site']['admin']);
        return in_array($uid,$_E['site']['admin']);
    }
}
