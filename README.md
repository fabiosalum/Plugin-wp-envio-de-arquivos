# eduCAT Documentos

Plugin WordPress para enviar e organizar arquivos e disponibilizar um catálogo público por categoria e ano.

- Envio em lote pelo painel.
- Categorias expansíveis com paginação.
- Ativação e desativação de documentos e categorias.
- Shortcode `[educat_documentos]`, com suporte ao shortcode anterior.
- Atualizações integradas ao WordPress a partir das Releases deste repositório.

## Instalação

Baixe **f2-documentos-institucionais.zip** nos arquivos anexados à [última Release](https://github.com/fabiosalum/Plugin-wp-envio-de-arquivos/releases/latest). Em Plugins → Adicionar novo → Enviar plugin, selecione o ZIP e ative ou substitua a instalação atual.

Para começar a receber atualizações pelo painel, instale a versão 1.3.0 ou posterior uma vez. Depois, use **Documentos → Atualizações → Verificar atualizações** ou a tela de plugins do WordPress.

O plugin consulta o arquivo público `update.json` da última Release. Ele confere versão, requisitos de WordPress e PHP e a integridade SHA-256 do ZIP antes de instalá-lo. A consulta usa cache de até uma hora. A instalação segue a escolha de atualização do administrador no WordPress.

## Publicar uma nova versão

1. Ajuste o campo `Version` e a constante `VERSION` em `f2-documentos-institucionais.php`.
2. Atualize o `Stable tag` em `readme.txt`. Se necessário, ajuste os requisitos de PHP e WordPress no cabeçalho do plugin.
3. Envie as alterações para `main`.

O workflow valida a sintaxe PHP, monta o ZIP com a pasta `f2-documentos-institucionais`, gera `update.json` e publica uma Release. Uma versão já publicada é preservada. Para uma nova publicação, incremente o número da versão. Também é possível iniciar o workflow na aba Actions.

Para gerar os arquivos localmente, execute `python3 scripts/build-release.py`. Os resultados ficam em `dist/`.

## Dados do site

Os documentos e as categorias ficam no banco de dados do WordPress. Os arquivos enviados ficam na biblioteca de mídia. Atualizar, desativar ou excluir o plugin preserva esses dados. Desativar um documento ou uma categoria controla sua exibição no catálogo. Os links diretos dos arquivos continuam públicos.

## Requisitos

WordPress 6.4 ou superior e PHP 7.4 ou superior.
