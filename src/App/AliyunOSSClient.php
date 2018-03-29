<?php
/**
 * Created by AliyunOSS.php.
 * User: yaoyiqiang
 * Date: 2018/2/1
 * Time: PM7:28
 */

namespace Yaococo\AliyunOSS;


use Illuminate\Support\Facades\Config;
use OSS\Core\OssException;
use OSS\Core\OssUtil;
use OSS\Model\CorsConfig;
use OSS\Model\CorsRule;
use OSS\Model\LifecycleAction;
use OSS\Model\LifecycleConfig;
use OSS\Model\LifecycleRule;
use OSS\Model\LiveChannelConfig;
use OSS\Model\RefererConfig;
use OSS\Model\WebsiteConfig;
use OSS\OssClient;

class AliyunOSSClient
{
    //公共的实例化方法
    private static function getOssClient()
    {
        try {
            $ossClient = new OssClient(
                Config::get('aliyunoss.OSSAccessKeyId'),
                Config::get('aliyunoss.OSSAccessKeySecret'),
                Config::get('aliyunoss.OSSEndpoint'), false);
        } catch (OssException $e) {
            static::responseError(__FUNCTION__ . "creating OssClient instance: FAILED\n");
        }
        return $ossClient;
    }

    //处理错误信息
    private static function responseError($message)
    {
        exit($message);
    }

    /*
    |--------------------------------------------------------------------------
    | Bucket 操作
    |--------------------------------------------------------------------------
    |
    | OSS Bucket 操作
    |
    */

    /**
     * 创建 Bucket
     *
     * @param $buckName
     * @return null
     */
    public static function createBucket($buckName)
    {
        /**
         * 参数说明：
         *
         * acl 指的是bucket的访问控制权限，有三种，私有读写，公共读私有写，公共读写。
         * 私有读写就是只有bucket的拥有者或授权用户才有权限操作
         * 三种权限分别对应 [
         * OssClient::OSS_ACL_TYPE_PRIVATE，
         * OssClient::OSS_ACL_TYPE_PUBLIC_READ,
         * OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE
         * ]
         */
        try {
            return self::getOssClient()->createBucket($buckName, OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE);
        } catch (OssException $e) {
            static::responseError("创建Bucket出错了");
            return null;
        }
    }

    /**
     * 判断 Bucket 是否存
     *
     * @param $buckName
     * @return bool|null
     */
    public static function doesBucketExist($buckName)
    {
        try {
            return self::getOssClient()->doesBucketExist($buckName);
        } catch (OssException $e) {
            static::responseError("判断bucket是否存在出错了");
        }
    }

    /**
     * 删除 Bucket
     *
     * 说明：
     * 如果Bucket不为空（Bucket中有Object，或者有分块上传的碎片），则Bucket无法删除。
     * 必须删除Bucket中的所有Object以及碎片后，Bucket才能成功删除
     *
     * @param $buckName
     * @return null
     */
    public static function deleteBucket($buckName)
    {
        try {
            $result = self::getOssClient()->deleteBucket($buckName);
        } catch (OssException $e) {
            static::responseError("删除Bucket错误");
        }
        return json_encode($result);
    }

    /**
     * 设置/修改 Bucket 的 ACL
     *
     * @param $buckName
     * @param $acl 可选值 [1-'private', 2-'public-read', 2-'public-read-write']
     * @return null|void
     */
    public static function putBucketAcl($buckName, $acl)
    {
        switch ($acl) {
            case 1:
                $acl = OssClient::OSS_ACL_TYPE_PRIVATE;
                break;
            case 2:
                $acl = OssClient::OSS_ACL_TYPE_PUBLIC_READ;
                break;
            case 3:
                $acl = OssClient::OSS_ACL_TYPE_PUBLIC_READ_WRITE;
                break;
            default:
                exit("ACL 权限参数不合法");
                break;
        }
        try {
            $result = self::getOssClient()->putBucketAcl($buckName, $acl);
        } catch (OssException $e) {
            static::responseError("设置Bucket的ACL 权限错误");
        }
        return json_encode($result);
    }

    /**
     * 获取 Bucket 的 ACL 配置
     *
     * @param $buckName
     * @return string|void
     */
    public static function getBucketAcl($buckName)
    {
        try {
            return json_encode(self::getOssClient()->getBucketAcl($buckName));
        } catch (OssException $e) {
            static::responseError("获取Bucket ACL 信息错误");
        }
    }

    /**
     * 列出用户所有的 Bucket
     *
     * @return string|void
     */
    public static function listBuckets()
    {
        $bucketList = null;
        try {
            $bucketListInfo = self::getOssClient()->listBuckets();
        } catch (OssException $e) {
            static::responseError("列出该用户的所有的Bucket错误");
        }
        $bucketList = $bucketListInfo->getBucketList();
        return json_encode($bucketList);
    }

    /*
    |--------------------------------------------------------------------------
    | Bucket 跨域资源共享(CORS)的规则
    |--------------------------------------------------------------------------
    |
    | OSS Bucket 操作跨域资源共享(CORS)的规则
    |
    */

    /**
     * 在指定的bucket上设定一个跨域资源共享(CORS)的规则，如果原规则存在则覆盖原规则
     *
     * @param $buckName
     * @param array $allowedHeaders
     * @param array $allowedOrigins
     * @param array $allowedMethods
     * @return null
     */
    public static function putBucketCors($buckName, array $allowedHeaders, array $allowedOrigins, array $allowedMethods)
    {
        $corsConfig = new CorsConfig();
        $rule = new CorsRule();
        $rule->addAllowedHeader($allowedHeaders);
        $rule->addAllowedOrigin($allowedOrigins);
        $rule->addAllowedMethod($allowedMethods);
        $rule->setMaxAgeSeconds(100);
        $corsConfig->addRule($rule);
        try {
            $result = self::getOssClient()->putBucketCors($buckName, $corsConfig);
        } catch (OssException $e) {
            static::responseError("设置bucket的cors配置错误");
        }
        return $result;
    }

    /**
     * 获取 Bucket的cors配置
     *
     * @param $buckName
     * @return string
     */
    public static function getBucketCors($buckName)
    {
        $corsConfig = null;
        try {
            $corsConfig = self::getOssClient()->getBucketCors($buckName);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return json_encode($corsConfig);
    }

    /**
     * 删除 Bucket 的 Cors 配置
     *
     * @param $buckName
     * @return null
     */
    public static function deleteBucketCors($buckName)
    {
        try {
            $result = self::getOssClient()->deleteBucketCors($buckName);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return $result;
    }


    /*
    |--------------------------------------------------------------------------
    | Bucket 生命周期设置
    |--------------------------------------------------------------------------
    |
    | OSS Bucket 操作生命周期
    | Lifecycle开启后，OSS将按照配置，定期自动删除或转储与Lifecycle规则相匹配的Object
    |
    */

    /**
     * 设置Bucket的LifeCycle配置
     *
     * @param $buckName
     * @return bool
     */
    public static function putBucketLifecycle($buckName)
    {
        $lifecycleConfig = new LifecycleConfig();
        $actions = array();
        $actions[] = new LifecycleAction(
            OssClient::OSS_LIFECYCLE_EXPIRATION,
            OssClient::OSS_LIFECYCLE_TIMING_DAYS, 3);
        $lifecycleRule = new LifecycleRule("delete obsoleted files", "obsoleted/", "Enabled", $actions);
        $lifecycleConfig->addRule($lifecycleRule);
        $actions = array();
        $actions[] = new LifecycleAction(OssClient::OSS_LIFECYCLE_EXPIRATION, OssClient::OSS_LIFECYCLE_TIMING_DATE, '2022-10-12T00:00:00.000Z');
        $lifecycleRule = new LifecycleRule("delete temporary files", "temporary/", "Enabled", $actions);
        $lifecycleConfig->addRule($lifecycleRule);
        try {
            self::getOssClient()->putBucketLifecycle($buckName, $lifecycleConfig);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }

    /**
     * 获取Bucket的LifeCycle配置
     *
     * @param $buckName
     * @return string
     */
    public static function getBucketLifecycle($buckName)
    {
        $lifecycleConfig = null;
        try {
            $lifecycleConfig = self::getOssClient()->getBucketLifecycle($buckName);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return json_encode($lifecycleConfig->serializeToXml());
    }

    /**
     * 删除Bucket的LifeCycle配置
     *
     * @param $buckName
     * @return bool
     */
    public static function deleteBucketLifecycle($buckName)
    {
        try {
            self::getOssClient()->deleteBucketLifecycle($buckName);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Bucket 日志配置
    |--------------------------------------------------------------------------
    |
    | OSS Bucket 日志配置
    |
    */

    /**
     * 开启Bucket访问日志记录功能，只有Bucket的所有者才能更改
     *
     * @param $bucketName
     * @return bool
     */
    public static function putBucketLogging($bucketName)
    {
        $option = array();
        //访问日志存放在本bucket下
        $targetBucket = $bucketName;
        $targetPrefix = "access.log";
        try {
            self::getOssClient()->putBucketLogging($bucketName, $targetBucket, $targetPrefix, $option);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }

    /**
     * 获取Bucket的访问日志配置情况
     *
     * @param $bucketName
     * @return bool
     */
    public static function getBucketLogging($bucketName)
    {
        $loggingConfig = null;
        $options = array();
        try {
            $loggingConfig = self::getOssClient()->getBucketLogging($bucketName, $options);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        print($loggingConfig->serializeToXml() . "\n");
        return true;

    }

    /**
     * 删除Bucket的访问日志
     *
     * @param $bucketName
     * @return bool
     */
    public static function deleteBucketLogging($bucketName)
    {
        try {
            self::getOssClient()->deleteBucketLogging($bucketName);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Bucket 防盗链
    |--------------------------------------------------------------------------
    |
    | OSS Bucket 防盗链配置
    |
    */

    /**
     * 设置Bucket的防盗链配置
     *
     * @param array $referers
     * @param $bucketName
     * @return bool
     */
    public static function putBucketReferer(array $referers, $bucketName)
    {
        $refererConfig = new RefererConfig();
        $refererConfig->setAllowEmptyReferer(true);
        if (count($referers)) {
            foreach ($referers as $referer) {
                $refererConfig->addReferer($referer);
            }
        }
        try {
            self::getOssClient()->putBucketReferer($bucketName, $refererConfig);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }

    /**
     * 获取Bucket的Referer配置情况
     *
     * @param $bucketName
     * @return bool
     */
    public static function getBucketReferer($bucketName)
    {
        $refererConfig = null;
        try {
            $refererConfig = self::getOssClient()->getBucketReferer($bucketName);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        print($refererConfig->serializeToXml() . "\n");
        return true;

    }

    /**
     * 删除bucket的防盗链配置
     * Referer白名单不能直接清空，只能通过重新设置来覆盖之前的规则。
     *
     * @param $bucketName
     * @return bool
     */
    public static function deleteBucketReferer($bucketName)
    {
        $refererConfig = new RefererConfig();
        try {
            self::getOssClient()->putBucketReferer($bucketName, $refererConfig);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Bucket 静态网站托管模式配置
    |--------------------------------------------------------------------------
    |
    | OSS Bucket 静态网站托管模式配置
    |
    */

    /**
     * 将Bucket设置成静态网站托管模式
     *
     * @param $bucketName
     * @return bool
     */
    public static function putBucketWebsite($bucketName)
    {
        $websiteConfig = new WebsiteConfig("index.html", "error.html");
        try {
            self::getOssClient()->putBucketWebsite($bucketName, $websiteConfig);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }

    /**
     * 获取bucket的静态网站托管状态
     *
     * @param $bucketName
     * @return bool
     */
    public static function getBucketWebsite($bucketName)
    {
        $websiteConfig = null;
        try {
            $websiteConfig = self::getOssClient()->getBucketWebsite($bucketName);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        print($websiteConfig->serializeToXml() . "\n");
        return true;
    }

    /**
     * 删除bucket的静态网站托管模式配置
     *
     * @param $bucketName
     * @return bool
     */
    public static function deleteBucketWebsite($bucketName)
    {
        try {
            self::getOssClient()->deleteBucketWebsite($bucketName);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return true;
    }


    /*
    |--------------------------------------------------------------------------
    | Bucket 图片操作
    |--------------------------------------------------------------------------
    |
    | OSS Bucket 图片操作
    |
    |
    | 1、图片支持的格式只能是：jpg、png、bmp、gif、webp、tiff
    | 2、文件大小不能超过20MB
    | 3、使用图片旋转时图片的宽或者高不能超过4096
    | 4、对于缩略图：对缩略后的图片大小有限制，目标缩略图宽与高的乘积不能超过4096 x 4096，
    |    且单边长度不能超过4096 x 4
    | 5、当只指定宽度或者高度时，在等比缩放的情况下，都会默认进行单边的缩放。
    |    在固定宽高的模式下，会默认宽高一样的情况下进行缩略
    |
    */

    /**
     * 上传一张图片到OSS上
     *
     * @param $bucketName
     * @param $objectName
     * @param $filePath
     * @param null $options
     * @return null
     */
    public static function uploadOneImage($bucketName, $objectName, $filePath, $options = null)
    {
        //上传图片文件到OSS服务器上
        return self::getOssClient()->uploadFile($bucketName, $objectName, $filePath, $options);
    }

    /**
     * 图片缩放
     *
     *
     * 参数示例:m_fixed,h_100,w_100
     * @param $bucketName
     * @param $object
     * @param $downloadPath
    @缩放的模式
     *      - lfit：等比缩放，限制在设定在指定w与h的矩形内的最大图片。
     *      - mfit：等比缩放，延伸出指定w与h的矩形框外的最小图片。
     *      - fill：固定宽高，将延伸出指定w与h的矩形框外的最小图片进行居中裁剪。
     *      - pad：固定宽高，缩略填充。
     *      - fixed：固定宽高，强制缩略
     *
     *      w - 指定目标缩略图的宽度 [1-4096]
     *      h - 指定目标缩略图的高度 [1-4096]
     *
     *      limit - 指定当目标缩略图大于原图时是否处理。值是 1 表示不处理；值是 0 表示处理。0/1, 默认是 1
     *      color - 当缩放模式选择为pad（缩略填充）时，可以选择填充的颜色(默认是白色)参数的填写方式：
     *              采用16进制颜色码表示，如00FF00（绿色）。[000000-FFFFFF]
     * @param $params
     * @return string
     */
    public static function resizeImage($bucketName, $object, $downloadPath, $params)
    {
        static::dealImage($bucketName, $object, $downloadPath, $params, 'resize');
    }

    /**
     * 图片剪切
     *
     * 参数示例:"w_100,h_100,x_100,y_100,r_1"
     * @param $bucketName
     * @param $object
     * @param $downloadPath
     * @param $params
     */
    public static function cropImage($bucketName, $object, $downloadPath, $params)
    {
        static::dealImage($bucketName, $object, $downloadPath, $params, 'crop');
    }

    /**
     * 图片旋转
     *
     * @param $bucketName
     * @param $object
     * @param $downloadPath
     * @param $params 参数示例：90
     */
    public static function rotateImage($bucketName, $object, $downloadPath, $params)
    {
        static::dealImage($bucketName, $object, $downloadPath, $params, 'rotate');
    }

    /**
     * 图片锐化
     *
     * @param $bucketName
     * @param $object
     * @param $downloadPath
     * @param $params 参数示例：100
     */
    public static function sharpenImage($bucketName, $object, $downloadPath, $params)
    {
        static::dealImage($bucketName, $object, $downloadPath, $params, 'sharpen');
    }

    /**
     * 图片水印
     *
     * @param $bucketName
     * @param $object
     * @param $downloadPath
     * @param $params 参数示例：text_SGVsbG8g5Zu-54mH5pyN5YqhIQ
     */
    public static function waterMarkImage($bucketName, $object, $downloadPath, $params)
    {
        static::dealImage($bucketName, $object, $downloadPath, $params, 'watermark');
    }

    /**
     * 图片格式转换
     *
     * @param $bucketName
     * @param $object
     * @param $downloadPath
     * @param $params 参数示例：png
     */
    public static function formatImage($bucketName, $object, $downloadPath, $params)
    {
        static::dealImage($bucketName, $object, $downloadPath, $params, 'format');
    }

    /**
     * 获取图片信息
     *
     * @param $bucketName
     * @param $object
     * @param $downloadPath
     * @param $params
     */
    public static function infoImage($bucketName, $object, $downloadPath, $params)
    {
        static::dealImage($bucketName, $object, $downloadPath, $params, 'info');
    }

    /**
     * 生成一个带签名的可用于浏览器直接打开的url, URL的有效期默认是3600秒
     *
     * @param $bucketName
     * @param $object
     * @param int $timeOut
     * @param $params 缩放/剪裁等等
     * @return string
     */
    public static function openSignUrl($bucketName, $object, $timeOut = 3600, $params)
    {
        $options = array(
            OssClient::OSS_PROCESS => "image/resize,m_lfit,h_100,w_100",
        );
        return self::getOssClient()->signUrl($bucketName, $object, $timeOut, "GET", $options);
    }

    //公共处理图片
    private function dealImage($bucketName, $object, $downloadPath, $params, $functionName)
    {
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $downloadPath,
            OssClient::OSS_PROCESS => "image/resize," . $params,);
        self::getOssClient()->getObject($bucketName, $object, $options);
        static::printImage("image" . ucfirst($functionName), $downloadPath);
    }

    //私有方法
    private function printImage($func, $imageFile)
    {
        $array = getimagesize($imageFile);
        Common::println("$func, image width: " . $array[0]);
        Common::println("$func, image height: " . $array[1]);
        Common::println("$func, image type: " . ($array[2] === 2 ? 'jpg' : 'png'));
        Common::println("$func, image size: " . ceil(filesize($imageFile)));
    }



    /*
    |--------------------------------------------------------------------------
    | 音视频上传到OSS
    |--------------------------------------------------------------------------
    |
    | 用户可以使用RTMP协议将音视频数据上传到OSS，转储为指定格式的音视频文件
    | 只能使用RTMP推流的方式，不支持拉流。
    | 必须包含视频流，且视频流格式为H264。
    | 音频流是可选的，并且只支持AAC格式，其他格式的音频流会被丢弃。
    | 转储只支持HLS协议。
    | 一个LiveChannel同时只能有一个客户端向其推流。
    */


    /**
     * 创建一个直播频道
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return string
     */
    public static function putBucketLiveChannel($bucketName, $liveChannelName)
    {
        $config = new LiveChannelConfig(array(
            'description' => 'live channel test',
            'type' => 'HLS',
            'fragDuration' => 10,
            'fragCount' => 5,
            'playListName' => 'playlist.m3u8'
        ));
        $info = self::getOssClient()->putBucketLiveChannel($bucketName, $liveChannelName, $config);
        return json_encode($info);
    }

    /**
     * 对创建好的频道，可以使用listBucketLiveChannels来进行列举已达到管理的目的。
     * prefix可以按照前缀过滤list出来的频道。
     * max_keys表示迭代器内部一次list出来的频道的最大数量，这个值最大不能超过1000，不填写的话默认为100。
     *
     *
     * @param $bucketName
     * @return string
     */
    public static function listBucketLiveChannels($bucketName)
    {
        $list = self::getOssClient()->listBucketLiveChannels($bucketName);
        return json_encode($list);
    }

    /**
     * 创建直播频道之后拿到推流用的play_url
     *（rtmp推流的url，如果Bucket不是公共读写权限那么还需要带上签名，见下文示例）
     * 和推流用的publish_url（推流产生的m3u8文件的url）
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return \OSS\推流地址
     */
    public static function signRTMPUrl($bucketName, $liveChannelName)
    {
        return self::getOssClient()->signRtmpUrl($bucketName, $liveChannelName, 3600);
        //return self::getOssClient()->signRtmpUrl($bucketName, $liveChannelName, 3600, array('params' => array('playlistName' => 'playlist.m3u8')));
    }

    /**
     * 创建好直播频道，如果想把这个频道禁用掉（断掉正在推的流或者不再允许向一个地址推流），
     * 应该使用putLiveChannelStatus接口，将频道的status改成“Disabled”，
     * 如果要将一个禁用状态的频道启用，那么也是调用这个接口，将status改成“Enabled”
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return null
     */
    public static function putLiveChannelStatus($bucketName, $liveChannelName)
    {
        $info = static::getLiveChannelInfo($bucketName, $liveChannelName);
        $status = $info->getStatus() == "enabled" ? "disabled" : "enabled";
        return self::getOssClient()->putLiveChannelStatus($bucketName, $liveChannelName, $status);
    }

    /**
     * 获取指定频道的信息
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return \OSS\GetLiveChannelInfo
     */
    public static function getLiveChannelInfo($bucketName, $liveChannelName)
    {
        return self::getOssClient()->getLiveChannelInfo($bucketName, $liveChannelName);
    }

    /**
     * 如果想查看一个频道历史推流记录，可以调用getLiveChannelHistory。
     * 目前最多可以看到10次推流的记录
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return string
     */
    public static function getLiveChannelHistory($bucketName, $liveChannelName)
    {
        $history = self::getOssClient()->getLiveChannelHistory($bucketName, $liveChannelName);
        return json_encode($history->getLiveRecordList());
    }

    /**
     *  对于正在推流的频道调用getLiveChannelStatus可以获得流的状态信息。
     * 如果频道正在推流，那么stat_result中的所有字段都有意义。
     * 如果频道闲置或者处于“Disabled”状态，那么status为“Idle”或“Disabled”，其他字段无意义。
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return string
     */
    public static function getLiveChannelStatus($bucketName, $liveChannelName)
    {
        $status = self::getOssClient()->getLiveChannelStatus($bucketName, $liveChannelName);
        return json_encode($status);
    }

    /**
     *  如果希望利用直播推流产生的ts文件生成一个点播列表，可以使用postVodPlaylist方法。
     *  指定起始时间为当前时间减去60秒，结束时间为当前时间，这意味着将生成一个长度为60秒的点播视频。
     *  播放列表指定为“vod_playlist.m3u8”，也就是说这个接口调用成功之后会在OSS上生成一个名叫“vod_playlist.m3u8”的播放列表文件。
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return string
     */
    public static function postVodPlaylist($bucketName, $liveChannelName)
    {
        $currentTime = time();
        $result = self::getOssClient()->postVodPlaylist($bucketName, $liveChannelName, "vod_playlist.m3u8",
            array('StartTime' => $currentTime - 60, 'EndTime' => $currentTime)
        );
        return json_encode($result);
    }

    /**
     * 删除直播频道
     *
     * @param $bucketName
     * @param $liveChannelName
     * @return null
     */
    public static function deleteBucketLiveChannel($bucketName, $liveChannelName)
    {
        return self::getOssClient()->deleteBucketLiveChannel($bucketName, $liveChannelName);
    }


    /*
    |--------------------------------------------------------------------------
    | 多文件上传
    |--------------------------------------------------------------------------
    |
    | 使用场景（不限于）:
    | 需要支持断点上传。
    | 上传超过100MB大小的文件。
    | 网络条件较差，和OSS的服务器之间的链接经常断开。
    | 上传文件之前，无法确定上传文件的大小。
    |
    | TODO multiUploadFile是可以设置有回调函数的 callback ，此版本暂未实现
    |
    */

    /**
     * 分片文件上传
     * 使用分片上传接口上传文件, 接口会根据文件大小决定是使用普通上传还是分片上传
     *
     * @param $bucketName
     * @param $object "test/multipart-test.txt"
     * @param $file   __FILE__
     * @param array $options array()
     * @return bool
     */
    public static function multiUploadFile($bucketName, $object, $file, array $options = [])
    {
        try {
            return self::getOssClient()->multiuploadFile($bucketName, $object, $file, $options);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
    }

    /**
     * 使用基本的api分阶段进行分片上传
     * @param $bucketName
     * @param $object "test/multipart-test.txt"
     * @return bool|null
     */
    public static function putObjectByRawAPIs($bucketName, $object)
    {
        $ossClient = self::getOssClient();
        /**
         *  step 1. 初始化一个分块上传事件, 也就是初始化上传Multipart, 获取upload id
         */
        try {
            $uploadId = $ossClient->initiateMultipartUpload($bucketName, $object);
        } catch (OssException $e) {
            self::responseError(__FUNCTION__ . ": initiateMultipartUpload FAILED\n" . "=====" . $e->getErrorMessage());
        }
        /*
         * step 2. 上传分片
         */
        $partSize = 10 * 1024 * 1024;
        $uploadFile = __FILE__;
        $uploadFileSize = filesize($uploadFile);
        $pieces = $ossClient->generateMultiuploadParts($uploadFileSize, $partSize);
        $responseUploadPart = array();
        $uploadPosition = 0;
        $isCheckMd5 = true;
        foreach ($pieces as $i => $piece) {
            $fromPos = $uploadPosition + (integer)$piece[$ossClient::OSS_SEEK_TO];
            $toPos = (integer)$piece[$ossClient::OSS_LENGTH] + $fromPos - 1;
            $upOptions = array(
                $ossClient::OSS_FILE_UPLOAD => $uploadFile,
                $ossClient::OSS_PART_NUM => ($i + 1),
                $ossClient::OSS_SEEK_TO => $fromPos,
                $ossClient::OSS_LENGTH => $toPos - $fromPos + 1,
                $ossClient::OSS_CHECK_MD5 => $isCheckMd5,
            );
            if ($isCheckMd5) {
                $contentMd5 = OssUtil::getMd5SumForFile($uploadFile, $fromPos, $toPos);
                $upOptions[$ossClient::OSS_CONTENT_MD5] = $contentMd5;
            }
            //2. 将每一分片上传到OSS
            try {
                $responseUploadPart[] = $ossClient->uploadPart($bucketName, $object, $uploadId, $upOptions);
            } catch (OssException $e) {
                self::responseError(__FUNCTION__ . ": initiateMultipartUpload, uploadPart - part#{$i} FAILED\n" . "====" . $e->getMessage() . "\n");
            }
        }
        $uploadParts = array();
        foreach ($responseUploadPart as $i => $eTag) {
            $uploadParts[] = array(
                'PartNumber' => ($i + 1),
                'ETag' => $eTag,
            );
        }
        /**
         * step 3. 完成上传
         */
        try {
            return $ossClient->completeMultipartUpload($bucketName, $object, $uploadId, $uploadParts);
        } catch (OssException $e) {
            self::responseError(__FUNCTION__ . ": completeMultipartUpload FAILED\n" . "====" . $e->getMessage() . "\n");
        }
        return false;
    }

    /**
     * 上传本地目录内的文件或者目录到指定bucket的指定prefix的object中
     *
     * @param string $bucketName bucket名称
     * @param string $prefix 需要上传到的object的key前缀，可以理解成bucket中的子目录，结尾不能是'/'，接口中会补充'/'
     * @param string $localDirectory 需要上传的本地目录
     * @return array|bool
     */
    public static function uploadDir($bucketName, $prefix, $localDirectory)
    {
        try {
            return self::getOssClient()->uploadDir($bucketName, $prefix, $localDirectory);
        } catch (OssException $e) {
            self::responseError(__FUNCTION__ . ": FAILED\n" . "=====" . $e->getMessage() . "\n");
        }
        return false;
    }

    /**
     * 获取当前未完成的分片上传列表
     *
     * @param $bucketName
     * @return string
     */
    public static function listMultipartUploads($bucketName)
    {
        $options = array(
            'max-uploads' => 100,
            'key-marker' => '',
            'prefix' => '',
            'upload-id-marker' => ''
        );
        try {
            $listMultipartUploadInfo = self::getOssClient()->listMultipartUploads($bucketName, $options);
        } catch (OssException $e) {
            self::responseError(__FUNCTION__ . ": listMultipartUploads FAILED\n" . "=====" . $e->getMessage() . "\n");
        }
        $listUploadInfo = $listMultipartUploadInfo->getUploads();
        return json_encode($listUploadInfo);
    }


    /*
    |--------------------------------------------------------------------------
    | Object 操作
    |--------------------------------------------------------------------------
    |
    | Object 相关操作
    |
    */


    /**
     * 创建虚拟目录
     *
     * @param $bucketName
     * @return bool|null
     */
    public static function createObjectDir($bucketName)
    {
        try {
            return self::getOssClient()->createObjectDir($bucketName, "dir");
        } catch (OssException $e) {
            self::responseError($e->getMessage() . "\n");
        }
        return false;
    }

    /**
     * 把本地变量的内容到文件
     *
     * 简单上传,上传指定变量的内存值作为object的内容
     *
     * 回调参数示例：（详见阿里云OSS网站 Callback部分）
     * $url =
     * '{
     * "callbackUrl":"callback.oss-demo.com:23450",
     * "callbackHost":"oss-cn-hangzhou.aliyuncs.com",
     * "callbackBody":"bucket=${bucket}&object=${object}&etag=${etag}&size=${size}&mimeType=${mimeType}&imageInfo.height=${imageInfo.height}&imageInfo.width=${imageInfo.width}&imageInfo.format=${imageInfo.format}&my_var1=${x:var1}&my_var2=${x:var2}",
     * "callbackBodyType":"application/x-www-form-urlencoded"
     * }';
     * $var =
     * '{
     * "x:var1":"value1",
     * "x:var2":"值2"
     * }';
     *
     * @param $bucketName
     * @param $object "oss-php-sdk-test/upload-test-object-name.txt"
     * @param $content file_get_contents(__FILE__)
     * @param bool $callback
     * @param null $urlParams
     * @param null $varParams
     * @return bool|null
     * @internal param array $options array()
     */
    public static function putObject($bucketName, $object, $content, $callback = false, $urlParams = null, $varParams = null)
    {
        if ($callback) {
            $options = array(
                OssClient::OSS_CALLBACK => $urlParams,
                OssClient::OSS_CALLBACK_VAR => $varParams
            );
        } else {
            $options = array();
        }
        try {
            return self::getOssClient()->putObject($bucketName, $object, $content, $options);
        } catch (OssException $e) {
            self::responseError($e->getMessage() . "\n");
        }
        return false;
    }

    /**
     * 上传指定的本地文件内容
     *
     * @param $bucketName
     * @param $object "oss-php-sdk-test/upload-test-object-name.txt"
     * @param $filePath __FILE__
     * @param $options array()
     * @return bool|null
     */
    public static function uploadFile($bucketName, $object, $filePath, $options = array())
    {
        try {
            return self::getOssClient()->uploadFile($bucketName, $object, $filePath, $options);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return false;
    }

    /**
     * TODO 需要结合真实的场景进行调整
     * 列出Bucket内所有目录和文件, 注意如果符合条件的文件数目超过设置的max-keys，
     * 用户需要使用返回的nextMarker作为入参，通过循环调用ListObjects得到所有的文件，
     * 具体操作见下面的 listAllObjects 示例
     *
     *
     * $prefix = 'oss-php-sdk-test/';
     * $delimiter = '/';
     * $nextMarker = '';
     * $maxKeys = 1000;
     *
     *
     * @param $bucketName
     * @param $delimiter
     * @param $prefix
     * @param $maxKeys
     * @param $nextMarker
     */
    public static function listObjects($bucketName, $delimiter, $prefix, $maxKeys, $nextMarker)
    {
        $options = array(
            'delimiter' => $delimiter,
            'prefix' => $prefix,
            'max-keys' => $maxKeys,
            'marker' => $nextMarker,
        );
        try {
            $listObjectInfo = self::getOssClient()->listObjects($bucketName, $options);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        $objectList = $listObjectInfo->getObjectList(); // 文件列表
        $prefixList = $listObjectInfo->getPrefixList(); // 目录列表
        return [
            'objectList'=>$objectList,
            'prefixList'=>$prefixList
        ];

//        if (!empty($objectList)) {
//            print("objectList:\n");
//            foreach ($objectList as $objectInfo) {
//                print($objectInfo->getKey() . "\n");
//            }
//        }
//        if (!empty($prefixList)) {
//            print("prefixList: \n");
//            foreach ($prefixList as $prefixInfo) {
//                print($prefixInfo->getPrefix() . "\n");
//            }
//        }
    }

    /**
     * TODO 需要结合真实的场景进行调整
     * 列出Bucket内所有目录和文件， 根据返回的nextMarker循环得到所有Objects
     *
     * @param $bucketName
     */
    public static function listAllObjects($bucketName)
    {
        //构造dir下的文件和虚拟目录
        for ($i = 0; $i < 100; $i += 1) {
            self::getOssClient()->putObject($bucketName, "dir/obj" . strval($i), "hi");
            self::getOssClient()->createObjectDir($bucketName, "dir/obj" . strval($i));
        }

        $prefix = 'dir/';
        $delimiter = '/';
        $nextMarker = '';
        $maxkeys = 30;

        while (true) {
            $options = array(
                'delimiter' => $delimiter,
                'prefix' => $prefix,
                'max-keys' => $maxkeys,
                'marker' => $nextMarker,
            );
            var_dump($options);
            try {
                $listObjectInfo = self::getOssClient()->listObjects($bucketName, $options);
            } catch (OssException $e) {
                self::responseError($e->getErrorMessage());
            }
            // 得到nextMarker，从上一次listObjects读到的最后一个文件的下一个文件开始继续获取文件列表
            $nextMarker = $listObjectInfo->getNextMarker();
            $listObject = $listObjectInfo->getObjectList();
            $listPrefix = $listObjectInfo->getPrefixList();
            var_dump(count($listObject));
            var_dump(count($listPrefix));
            if ($nextMarker === '') {
                break;
            }
        }
    }

    /**
     * 获取object的内容
     *
     * 参数示例：
     * $object = "oss-php-sdk-test/upload-test-object-name.txt";
     * $options = array();
     *
     * @param $bucketName
     * @param $object
     * @param array $options
     * @return string
     */
    public static function getObject($bucketName, $object, $options = array())
    {
        try {
            $content = self::getOssClient()->getObject($bucketName, $object, $options);
        } catch (OssException $e) {
            self::responseError($e->getErrorMessage());
        }
        return json_encode($content);
    }

    /**
     * 设置符号链接
     *
     *  $symlink = "test-samples-symlink";
     * $object = "test-samples-object";
     *
     * @param $bucketName
     * @param $object
     * @param $symlink
     * @param string $content
     * @return bool|string
     */
    public static function putSymlink($bucketName, $object, $symlink, $content = "test-content")
    {
        try {
            self::getOssClient()->putObject($bucketName, $object, $content);
            self::getOssClient()->putSymlink($bucketName, $symlink, $object);
            return self::getOssClient()->getObject($bucketName, $symlink);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return false;
    }

    /**
     *  获取symlink(符号链接)
     *
     *  $symlink = "test-samples-symlink";
     * $object = "test-samples-object";
     *
     * @param $bucketName
     * @param $object
     * @param $symlink
     * @param string $content
     * @return bool
     */
    public static function getSymlink($bucketName, $object, $symlink, $content = "test-content")
    {
        try {
            self::getOssClient()->putObject($bucketName, $object, 'test-content');
            self::getOssClient()->putSymlink($bucketName, $symlink, $object);
            $content = self::getOssClient()->getSymlink($bucketName, $symlink);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return $content[OssClient::OSS_SYMLINK_TARGET] ? true : false;
    }

    /**
     * 获取object 将object下载到指定的文件
     *
     * 参数：
     * $object = "oss-php-sdk-test/upload-test-object-name.txt";
     * $localfile = "upload-test-object-name.txt";
     *
     * @param $bucketName
     * @param $object
     * @param $localFile
     * @return mixed
     */
    public static function getObjectToLocalFile($bucketName, $object, $localFile)
    {
        $options = array(
            OssClient::OSS_FILE_DOWNLOAD => $localFile,
        );
        try {
            self::getOssClient()->getObject($bucketName, $object, $options);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return $localFile;
    }

    /**
     * 拷贝一个在OSS上已经存在的object成另外一个object
     *
     * 参数示例:
     *  $fromBucket = $bucket;
     * $fromObject = "oss-php-sdk-test/upload-test-object-name.txt";
     * $toBucket = $bucket;
     * $toObject = $fromObject . '.copy';
     * $options = array();
     *
     * @param $fromBucket
     * @param $fromObject
     * @param $toBucket
     * @param $toObject
     * @param array $options
     * @return bool|null
     */
    public static function copyObject($fromBucket, $fromObject, $toBucket, $toObject, $options = array())
    {
        try {
            return self::getOssClient()->copyObject($fromBucket, $fromObject, $toBucket, $toObject, $options);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return false;
    }

    /**
     * 修改Object Meta
     * 利用copyObject接口的特性：当目的object和源object完全相同时，表示修改object的meta信息
     *
     * 示例参数：
     * array(
     * 'Cache-Control' => 'max-age=60',
     * 'Content-Disposition' => 'attachment; filename="xxxxxx"',
     * )
     *
     * @param $fromBucket
     * @param $fromObject
     * @param $toBucket
     * @param $toObject
     * @param array $optionHeaders
     * @return bool|null
     * @internal param $options
     */
    public static function modifyMetaForObject($fromBucket, $fromObject, $toBucket, $toObject, $optionHeaders = array())
    {
        $copyOptions = array(
            OssClient::OSS_HEADERS => $optionHeaders,
        );
        return self::copyObject($fromBucket, $fromObject, $toBucket, $toObject, $copyOptions);
    }

    /**
     * 获取Object的meta值
     *
     * @param $bucketName
     * @param $object
     * @return string
     */
    public static function getObjectMeta($bucketName, $object)
    {
        //$object = "oss-php-sdk-test/upload-test-object-name.txt";
        try {
            $objectMeta = self::getOssClient()->getObjectMeta($bucketName, $object);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return json_encode($objectMeta);
    }

    /**
     * 删除 Object
     *
     * @param $bucketName
     * @param $object
     * @return null
     */
    public static function deleteObject($bucketName, $object)
    {
        return self::getOssClient()->deleteObject($bucketName, $object);
    }

    /**
     * 删除批量的Objects
     *
     * @param $bucketName
     * @param array $objects
     * @return bool|\OSS\Http\ResponseCore
     */
    public static function deleteObjects($bucketName, array $objects)
    {
        try {
            return self::getOssClient()->deleteObjects($bucketName, $objects);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return false;
    }

    /**
     * 判断Object是否存在
     *
     * @param $bucketName
     * @param $object
     * @return bool
     */
    public static function doesObjectExist($bucketName, $object)
    {
        //$object = "oss-php-sdk-test/upload-test-object-name.txt";
        try {
            return self::getOssClient()->doesObjectExist($bucketName, $object);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Signature 操作
    |--------------------------------------------------------------------------
    |
    | Signature 相关操作
    |
    */

    /**
     * 支持生成get和put签名, 用户可以生成一个具有一定有效期的
     * 签名过的url
     *
     *
     * @param $bucketName
     * @param $object
     * @param $timeout
     * @param $method
     * @return string
     */
    public static function getSignedUrlForObject($bucketName, $object, $timeout, $method)
    {
        $method = strtoupper($method);
        if (!in_array($method, ['PUT', 'GET'])) {
            self::responseError("输入的方法不支持该操作");
        }
        try {
            $signedUrl = self::getOssClient()->signUrl($bucketName, $object, $timeout, $method);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return $signedUrl;
    }

    /**
     * 生成PutObject的签名url,主要用于私有权限下的写访问控制， 用户可以利用生成的signedUrl
     * 从文件上传文件
     *
     * @param $bucketName
     * @param $object
     * @param $timeout
     * @param $file
     * @param array $options
     * @return string
     */
    public static function getSignedUrlForPuttingObjectFromFile($bucketName, $object, $timeout, $file, $options = array())
    {
//        $file = __FILE__;
//        $object = "test/test-signature-test-upload-and-download.txt";
//        $timeout = 3600;
//        $options = array('Content-Type' => 'txt');
        try {
            $signedUrl = self::getOssClient()->signUrl($bucketName, $object, $timeout, "PUT", $options);
        } catch (OssException $e) {
            self::responseError($e->getMessage());
        }
        return $signedUrl;
    }


    /*
   |--------------------------------------------------------------------------
   | CallBack 操作
   |--------------------------------------------------------------------------
   |
   | CallBack 相关操作
   | 用户只需要在发送给OSS的请求中携带相应的Callback参数，即能实现回调。
   | 现在支持CallBack的API
   | 接口有：PutObject、PostObject、CompleteMultipartUpload。
   |
   */


}