<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait NavigationMenuTrait
{
    public function createNavMenu($nav_list, $main_content, $content_data, $color, $url)
    {
        $base_url = url($url);
        // dd($base_url, env('BASE_URL'));
        $html_nav_main = '<li id="myMainNavTab" class="list-group-item bg-dark border-0 py-1 overflow-auto"><div class="d-flex text-nowrap">';
        $html_nav_secondary = '<li id="mySecNavTab" class="list-group-item border-0 py-0 overflow-auto">';
        $html_main = '<div id="main-section" class="tab-content p-0" style="font-size:0.8rem">';

        foreach ($nav_list as $index => $item) {
            /* Nav main */
            $id = Str::slug($item['nav-main']);
            $html_nav_main .= '<a href="' . $base_url . '/' . $id . '/' . Str::slug($item['nav-secondary'][0]['name']) . '" id="' . $id . '" data-nav-id="' . $id . '" class="btn-nav-main c-' . $color . ' me-2">
                ' . $item['icon'] . $item['nav-main'] . '</a>';

            /* Nav Secondary */
            $html_nav_secondary .= '<div class="d-flex nav-secondary-tab py-1 d-none ' . $id . ' text-nowrap">';
            foreach ($item['nav-secondary'] as $index => $v) {
                $slug = Str::slug($v['name']);
                $html_nav_secondary .= '<a href="' . $base_url . '/' . $id . '/' . $slug . '" id="' . $slug . '-tab" class="nav-secondary c-' . $color . ' ' . $v['class'] . ' me-2" role="tab" data-bs-toggle="tab" data-bs-target="#' . $slug . '-tab-content" aria-controls="' . $slug . '-tab" aria-selected="false">' . $v['name'] . '</a>';
            }
            $html_nav_secondary .= '<div class="underline c-' . $color . '"></div></div>';

            /* Main */
            foreach ($item['nav-secondary'] as $index => $v) {
                $slug = Str::slug($v['name']);
                $html_main .=  '<div class="tab-pane tab-main fade" id="' . $slug . '-tab-content" role="tabpanel">' . view($main_content, [
                    'slug' => $slug,
                    'data' => $content_data,
                    'color' => $color
                ]) . '</div>';
            }
        }
        $html_nav_main .= '</div></li>';
        $html_nav_secondary .= '</li>';
        $html_main .= '</div>';

        $data['nav_list'] = $html_nav_main . $html_nav_secondary;
        $data['main_view'] = $html_main;

        return $data;
    }

    public function createSidebarMenu($sidebarList)
    {
        $html_sidebar = '';
        $html_content_sidebar = '';

        foreach ($sidebarList as $item) {
            $id = Str::slug($item);
            $html_sidebar .= '<li class="list-group-item py-2" data-bs-toggle="collapse" data-bs-target="#' . $id . '"
            aria-expanded="false" aria-controls="collapse" style="cursor: pointer;font-size: 0.8rem">' . $item . '<i
            class="bi bi-arrow-right float-end"></i></li>';

            $html_content_sidebar .= '<div class="collapse collapse-horizontal" id="' . $id . '">
            <div class="card card-body w-100 py-2" style="min-width: 60vw;">' . $item . '</div></div>';
        }


        $data['list'] = $html_sidebar;
        $data['content'] = $html_content_sidebar;

        return $data;
    }

    public function createSidebarPrint($sidebarList)
    {
        $html_print = '';

        foreach ($sidebarList as $item) {
            $id = Str::slug($item);
            $html_print .= '<li class="list-group-item py-2" data-bs-toggle="collapse" data-bs-target="#' . $id . '"
            aria-expanded="false" aria-controls="collapse" style="cursor: pointer;font-size: 0.8rem">' . $item . '<i class="bi bi-printer float-end"></i></li>';
        }


        $data = $html_print;

        return $data;
    }
}
