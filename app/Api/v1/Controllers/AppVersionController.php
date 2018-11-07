<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/23
 * Time: 上午10:24
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\Repository;
use App\Models\AppTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * @Resource("App版本检测")
 */
class AppVersionController extends Controller
{
    /**
     * 检测App版本
     *
     * > 目前支持的type
     * 1：Android
     * 2：iOS
     *
     * @Get("/app/version/check")
     *
     *
     * @Parameters({
     *      @Parameter("type", description="系统类型", type="integer", required=true),
     *      @Parameter("version", description="当前版本", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": {"latest_version": "最新版本号", "force_update": "当前版本是否需要强制更新", "download_url": "最新版下载链接"}}),
     *      @Response(400, body={"code": 40003, "message": "参数错误"})
     * })
     */
    public function check(Request $request)
    {
        $type = intval($request->get('type'));
        $version = $request->get('version');
        if (!$type || !$version)
        {
            return $this->resErrBad();
        }

        $repository = new Repository();

        $result = $repository->Cache($this->appCacheKey($type, $version), function () use ($type, $version)
        {
            $latest = DB
                ::table('app_versions')
                ->where('app_type', $type)
                ->orderBy('id', 'DESC')
                ->select('app_version', 'download_url')
                ->first();

            $force = DB
                ::table('app_versions')
                ->where('app_type', $type)
                ->where('app_version', $version)
                ->pluck('force_update')
                ->first();

            return [
                'latest_version' => $latest ? $latest->app_version : "0.0.0",
                'force_update' => $force == 1,
                'download_url' => $latest ? $latest->download_url : ""
            ];
        }, 'm');

        return $this->resOK($result);
    }

    public function create(Request $request)
    {
        $type = $request->get('type');
        $version = $request->get('version');

        $newId = DB
            ::table('app_versions')
            ->insertGetId([
                'app_type' => $type,
                'app_version' => $version,
                'force_update' => 0,
                'download_url' => $request->get('url'),
                'created_at' => Carbon::now()
            ]);

        if (!$newId)
        {
            return $this->resErrBad();
        }

        Redis::DEL($this->appCacheKey($type, $version));

        $newVersion = DB
            ::table('app_versions')
            ->where('id', $newId)
            ->first();

        return $this->resOK($newVersion);
    }

    public function delete(Request $request)
    {
        $type = $request->get('type');
        $version = $request->get('version');

        DB
            ::table('app_versions')
            ->where('app_type', $type)
            ->where('app_version', $version)
            ->delete();

        Redis::DEL($this->appCacheKey($type, $version));

        return $this->resNoContent();
    }

    public function toggleForce(Request $request)
    {
        $type = $request->get('type');
        $version = $request->get('version');
        $force = $request->get('force');

        DB
            ::table('app_versions')
            ->where('app_type', $type)
            ->where('app_version', $version)
            ->update([
                'force_update' => $force
            ]);

        Redis::DEL($this->appCacheKey($type, $version));

        return $this->resNoContent();
    }

    public function list()
    {
        $list = DB
            ::table('app_versions')
            ->orderBy('id', 'DESC')
            ->get();

        return $this->resOK($list);
    }

    public function uploadAppToken()
    {
        $auth = new \App\Services\Qiniu\Auth();
        $timeout = 3600;
        $uptoken = $auth->uploadToken(null, $timeout);

        return $this->resOK($uptoken);
    }

    public function setTemplates(Request $request)
    {
        $list = $request->get('list');

        foreach ($list as $item)
        {
            $version = $item['version'];
            $page = $item['page'];
            $url = $item['url'];

            $has = DB
                ::table('app_templates')
                ->where('version', $version)
                ->where('page', $page)
                ->count();

            if ($has)
            {
                DB
                    ::table('app_templates')
                    ->where('version', $version)
                    ->where('page', $page)
                    ->update([
                        'url' => $url
                    ]);
            }
            else
            {
                DB
                    ::table('app_templates')
                    ->insert([
                        'version' => $version,
                        'page' => $page,
                        'url' => $url
                    ]);
            }

            Redis::DEL($this->templateKey($version, $page));
            Redis::DEL('app-template-version-' . $version);
        }

        Redis::DEL('app-template-all');

        return $this->resNoContent();
    }

    /**
     * 获取 App 的 H5 模板
     *
     * > 目前支持的type
     * transactions
     *
     * @Get("/app/template")
     *
     *
     * @Parameters({
     *      @Parameter("page", description="哪个页面", type="integer", required=false),
     *      @Parameter("version", description="哪个版本", type="string", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body={"code": 0, "data": "模板数据"}),
     *      @Response(404, body={"code": 40401, "message": "不存在的模板"})
     * })
     */
    public function getTemplates(Request $request)
    {
        $version = $request->get('version');
        $page = $request->get('page');
        $repository = new Repository();

        if ($version && $page)
        {
            $result = $repository->Cache($this->templateKey($version, $page), function () use ($version, $page)
            {
                return DB
                    ::table('app_templates')
                    ->where('version', $version)
                    ->where('page', $page)
                    ->select('url')
                    ->first();
            });

            if (is_null($result))
            {
                return $this->resErrNotFound();
            }

            return $this->resOK($result);
        }

        if ($version)
        {
            $result = $repository->Cache('app-template-version-' . $version, function () use ($version)
            {
                return DB
                    ::table('app_templates')
                    ->where('version', $version)
                    ->select('page', 'url')
                    ->get()
                    ->toArray();
            });

            if (empty($result))
            {
                return $this->resErrNotFound();
            }

            return $this->resOK($result);
        }

        $result = $repository->Cache('app-template-all', function () use ($version)
        {
            return DB
                ::table('app_templates')
                ->select('page', 'url', 'version')
                ->get()
                ->toArray();
        });

        return $this->resOK($result);
    }

    protected function templateKey($version, $page)
    {
        return 'app-template-' . $page . '-' . $version;
    }

    protected function appCacheKey($type, $version)
    {
        return 'app_version-' . $type . '-' . $version;
    }
}