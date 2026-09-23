<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_responde_com_sucesso_para_visitantes(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertViewIs('public.home')
            ->assertSee('Conteúdos bem organizados')
            ->assertSee('O que é um CMS?')
            ->assertSee('sistema de gestão de conteúdos')
            ->assertSee('PCTECKSERV', escape: false);
    }

    public function test_homepage_apresenta_titulo_e_favicon_das_opcoes_do_site(): void
    {
        $siteOptions = app(\Pcteckserv\CmsCore\Support\SiteOptions::class);
        $siteOptions->setMany([
            'site_title' => 'Website Exemplo Teste',
            'site_icon_url' => 'https://exemplo.pt/favicon-custom.png',
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('<title>Website Exemplo Teste</title>', escape: false)
            ->assertSee('<link rel="icon" type="image/png" href="https://exemplo.pt/favicon-custom.png">', escape: false);
    }
}
