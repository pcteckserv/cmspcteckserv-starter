<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

class HomepageTest extends TestCase
{
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
}
