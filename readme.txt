=== eduCAT Documentos ===
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later

== Instalação e atualização ==

1. No WordPress, abra Plugins → Adicionar novo → Enviar plugin. Envie o ZIP sem extrair. Para atualizar a versão anterior, escolha a opção de substituir a instalação atual. Na primeira instalação, ative o plugin.
2. Em Documentos → Categorias, cadastre as categorias e, se necessário, suas subcategorias.
3. Em Documentos → Enviar, selecione os arquivos, as categorias e o ano de referência. Cada arquivo vira um registro, com título inicial extraído do nome.
4. Em Documentos → Enviar, clique em Editar a página do catálogo. Na primeira ativação, o plugin cria o rascunho Documentos Institucionais. Revise e publique. Se a instalação não tiver o rascunho, a tela oferece Criar página do catálogo.
5. Para usar outra página, insira [educat_documentos] em um bloco de shortcode do WordPress ou no widget Shortcode do Elementor. Para mudar o número de documentos por página de cada categoria, use [educat_documentos por_pagina="18"].

O shortcode anterior [f2_documentos] também renderiza a versão atual do catálogo. Documentos, categorias e páginas cadastrados na versão anterior são reaproveitados.

== Catálogo público ==

Clique no nome de uma categoria para abrir a lista de documentos. As categorias ficam recolhidas na primeira visita. Uma busca por título ou categoria abre os grupos correspondentes. Cada categoria tem sua própria paginação. Um documento em várias categorias ativas aparece em cada uma delas, mas é contado uma única vez no total do catálogo.

== Ativar e desativar ==

Em Documentos → Documentos, use a coluna Status no catálogo para ativar ou desativar um documento. Abra o registro para editar o título, as categorias e o ano. Um documento desativado continua no painel e sai dos resultados públicos.

Em Documentos → Categorias, use a mesma coluna para alterar o status de uma categoria. Categorias desativadas saem da busca pública e da tela de envio. Os documentos associados a elas aparecem quando o próprio documento está ativo e existe pelo menos uma categoria ativa vinculada. As subcategorias têm status próprio. Documentos e categorias cadastrados na versão anterior começam com o status ativo.

Os arquivos ficam na biblioteca de mídia do WordPress e têm URLs públicas desde o envio. Desativar ou mover um documento para a lixeira o retira do catálogo. Seu link direto continua acessível. Antes de excluir um arquivo em Mídia, confira se ele é usado em outras páginas.

O envio exige permissão para administrar o site e enviar arquivos. Cada lote aceita até 20 arquivos, sujeito aos limites do servidor e do WordPress. A tela mostra os formatos habilitados. Com um CDN de mídia em outro domínio, o navegador pode abrir o arquivo em vez de iniciar o download.

Desativar o plugin preserva os registros, arquivos, categorias e páginas.
