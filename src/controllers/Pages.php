<?php

namespace taust\controllers;

use Minz\Response;
use taust\models;
use taust\utils;

class Pages
{
    public function index()
    {
        $current_user = utils\CurrentUser::get();
        if (!$current_user) {
            return Response::redirect('login');
        }

        $pages = models\Page::daoToList('listAllOrderByTitle');

        return Response::ok('pages/index.phtml', [
            'pages' => $pages,
        ]);
    }

    public function new()
    {
        $current_user = utils\CurrentUser::get();
        if (!$current_user) {
            return Response::redirect('login');
        }

        return Response::ok('pages/new.phtml', [
            'title' => '',
        ]);
    }

    public function create($request)
    {
        $current_user = utils\CurrentUser::get();
        if (!$current_user) {
            return Response::redirect('login');
        }

        $title = $request->param('title');
        $csrf = $request->param('csrf');

        if (!\Minz\CSRF::validate($csrf)) {
            return Response::badRequest('pages/new.phtml', [
                'title' => $title,
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        $page = models\Page::init($title);
        $errors = $page->validate();
        if ($errors) {
            return Response::badRequest('pages/new.phtml', [
                'title' => $title,
                'errors' => $errors,
            ]);
        }

        $page->save();

        return Response::redirect('edit page', ['id' => $page->id]);
    }

    public function show($request)
    {
        $id = $request->param('id');
        $page = models\Page::find($id);
        if (!$page) {
            return Response::notFound('not_found.phtml');
        }

        return Response::ok('pages/show.phtml', [
            'page' => $page,
            'domains' => $page->domains(),
            'servers' => $page->servers(),
            'announcements_by_days' => $page->weekAnnouncements(),
            'type' => 'maintenance',
            'planned_at' => \Minz\Time::now(),
            'title' => '',
            'content' => '',
        ]);
    }

    public function feed($request)
    {
        $id = $request->param('id');
        $page = models\Page::find($id);
        if (!$page) {
            return Response::notFound('not_found.phtml');
        }

        $response = Response::ok('pages/feed.atom.xml.phtml', [
            'page' => $page,
            'announcements' => $page->announcements(),
        ]);
        $response->setHeader('Content-Type', 'application/atom+xml;charset=UTF-8');
        return $response;
    }

    public function style($request)
    {
        $id = $request->param('id');
        $page = models\Page::find($id);
        if (!$page) {
            return Response::notFound('not_found.phtml');
        }

        $response = Response::text(200, $page->style);
        $response->setHeader('Content-Type', 'text/css');
        return $response;
    }

    public function edit($request)
    {
        $current_user = utils\CurrentUser::get();
        if (!$current_user) {
            return Response::redirect('login');
        }

        $id = $request->param('id');
        $page = models\Page::find($id);
        if (!$page) {
            return Response::notFound('not_found.phtml');
        }

        $servers = models\Server::daoToList('listAllOrderById');
        $domains = models\Domain::daoToList('listAllOrderById');

        return Response::ok('pages/edit.phtml', [
            'page' => $page,
            'domain_ids' => array_column($page->domains(), 'id'),
            'server_ids' => array_column($page->servers(), 'id'),
            'servers' => $servers,
            'domains' => $domains,
            'hostname' => $page->hostname,
            'style' => $page->style,
            'locale' => $page->locale,
        ]);
    }

    public function update($request)
    {
        $current_user = utils\CurrentUser::get();
        if (!$current_user) {
            return Response::redirect('login');
        }

        $id = $request->param('id');
        $csrf = $request->param('csrf');
        $domain_ids = $request->paramArray('domain_ids', []);
        $server_ids = $request->paramArray('server_ids', []);
        $hostname = $request->param('hostname');
        $style = $request->param('style');
        $locale = $request->param('locale');

        $page = models\Page::find($id);
        $servers = models\Server::daoToList('listAllOrderById');
        $domains = models\Domain::daoToList('listAllOrderById');

        if (!$page) {
            return Response::notFound('not_found.phtml');
        }

        if (!\Minz\CSRF::validate($csrf)) {
            return Response::badRequest('pages/edit.phtml', [
                'page' => $page,
                'domain_ids' => $domain_ids,
                'server_ids' => $server_ids,
                'servers' => $servers,
                'domains' => $domains,
                'hostname' => $hostname,
                'style' => $style,
                'locale' => $locale,
                'error' => _('A security verification failed: you should retry to submit the form.'),
            ]);
        }

        $existing_page = models\Page::findBy([
            'hostname' => $hostname,
        ]);
        if ($existing_page && $existing_page->id !== $page->id && $hostname !== '') {
            return Response::badRequest('pages/edit.phtml', [
                'page' => $page,
                'domain_ids' => $domain_ids,
                'server_ids' => $server_ids,
                'servers' => $servers,
                'domains' => $domains,
                'hostname' => $hostname,
                'style' => $style,
                'locale' => $locale,
                'errors' => [
                    'hostname' => _('A page already has the same hostname.'),
                ],
            ]);
        }

        $page->hostname = $hostname;
        $page->style = $style;
        $page->locale = $locale;
        $page->save();

        models\PageToDomain::set($page->id, $domain_ids);
        models\PageToServer::set($page->id, $server_ids);

        return Response::redirect('edit page', ['id' => $page->id]);
    }

    public function delete($request)
    {
        $current_user = utils\CurrentUser::get();
        if (!$current_user) {
            return Response::redirect('login');
        }

        $id = $request->param('id');
        $csrf = $request->param('csrf');

        if (!\Minz\CSRF::validate($csrf)) {
            return Response::redirect('edit server', ['id' => $id]);
        }

        $page = models\Page::find($id);
        if (!$page) {
            return Response::notFound('not_found.phtml');
        }

        models\Page::delete($page->id);

        return Response::redirect('pages');
    }
}
