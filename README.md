# WP System REST API

Plugin WordPress que expõe informações do sistema via REST API protegida por autenticação.

## 📋 Descrição

O **WP System REST API** é um plugin simples mas robusto que cria um endpoint REST API customizado para fornecer informações detalhadas sobre sua instalação WordPress, incluindo:

- Versão do WordPress
- Versão do PHP
- Tema ativo (com informações de atualização)
- Lista completa de plugins instalados (ativos e inativos)
- Status de atualizações disponíveis

## ✨ Características

- **Seguro**: Protegido por autenticação usando Application Passwords (Senhas de Aplicação)
- **Completo**: Retorna todos os dados em um único payload JSON
- **Informativo**: Inclui informações sobre atualizações disponíveis
- **Simples**: Implementação direta e fácil de usar
- **Robusto**: Tratamento de erros adequado e código bem documentado

## 🚀 Instalação

### Método 1: Upload Manual

1. Baixe o arquivo `wp-system-rest-api.php`
2. Acesse o painel administrativo do WordPress
3. Vá em **Plugins > Adicionar novo > Fazer upload do plugin**
4. Selecione o arquivo e clique em **Instalar agora**
5. Ative o plugin

### Método 2: Via FTP/SFTP

1. Crie uma pasta chamada `wp-system-rest-api` no diretório `wp-content/plugins/`
2. Faça upload do arquivo `wp-system-rest-api.php` para essa pasta
3. Acesse o painel administrativo do WordPress
4. Vá em **Plugins** e ative o **WP System REST API**

### Método 3: Via WP-CLI

```bash
# Copie o plugin para o diretório de plugins
cp -r wp-system-rest-api /path/to/wordpress/wp-content/plugins/

# Ative o plugin
wp plugin activate wp-system-rest-api
```

## 🔐 Configuração de Autenticação

O plugin utiliza o sistema nativo de **Application Passwords** (Senhas de Aplicação) do WordPress.

### Criando uma Application Password

1. Acesse o painel administrativo do WordPress
2. Vá em **Usuários > Perfil**
3. Role até a seção **Senhas de aplicação** (Application Passwords)
4. Digite um nome para a aplicação (ex: "API Client")
5. Clique em **Adicionar nova senha de aplicação**
6. **Importante**: Copie a senha gerada imediatamente (ela não será exibida novamente)

## 📡 Uso da API

### Endpoint

```
GET /wp-json/wp-system/v1/info
```

### Autenticação

Use HTTP Basic Authentication com suas credenciais WordPress e a Application Password:

- **Username**: Seu nome de usuário WordPress
- **Password**: A Application Password gerada

### Exemplos de Requisições

#### cURL

```bash
curl -X GET https://seu-site.com/wp-json/wp-system/v1/info \
  -u "seu-usuario:xxxx xxxx xxxx xxxx xxxx xxxx"
```

#### cURL com cabeçalho Authorization

```bash
# Codifique suas credenciais em Base64
# Formato: usuario:senha
echo -n "seu-usuario:xxxx xxxx xxxx xxxx xxxx xxxx" | base64

# Use o resultado na requisição
curl -X GET https://seu-site.com/wp-json/wp-system/v1/info \
  -H "Authorization: Basic SEU_TOKEN_BASE64_AQUI"
```

#### JavaScript (Fetch API)

```javascript
const username = 'seu-usuario';
const password = 'xxxx xxxx xxxx xxxx xxxx xxxx';
const credentials = btoa(`${username}:${password}`);

fetch('https://seu-site.com/wp-json/wp-system/v1/info', {
  method: 'GET',
  headers: {
    'Authorization': `Basic ${credentials}`,
    'Content-Type': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Erro:', error));
```

#### JavaScript (Axios)

```javascript
const axios = require('axios');

axios.get('https://seu-site.com/wp-json/wp-system/v1/info', {
  auth: {
    username: 'seu-usuario',
    password: 'xxxx xxxx xxxx xxxx xxxx xxxx'
  }
})
.then(response => {
  console.log(response.data);
})
.catch(error => {
  console.error('Erro:', error.response?.data || error.message);
});
```

#### Python (Requests)

```python
import requests
from requests.auth import HTTPBasicAuth

url = 'https://seu-site.com/wp-json/wp-system/v1/info'
username = 'seu-usuario'
password = 'xxxx xxxx xxxx xxxx xxxx xxxx'

response = requests.get(url, auth=HTTPBasicAuth(username, password))

if response.status_code == 200:
    data = response.json()
    print(data)
else:
    print(f"Erro: {response.status_code}")
    print(response.json())
```

#### PHP

```php
<?php
$url = 'https://seu-site.com/wp-json/wp-system/v1/info';
$username = 'seu-usuario';
$password = 'xxxx xxxx xxxx xxxx xxxx xxxx';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    print_r($data);
} else {
    echo "Erro: $http_code\n";
    print_r(json_decode($response, true));
}
?>
```

## 📦 Estrutura da Resposta

### Resposta de Sucesso (200 OK)

```json
{
  "wordpress_version": "6.4.2",
  "php_version": "8.2.0",
  "theme": {
    "name": "Twenty Twenty-Four",
    "version": "1.0",
    "update_available": false,
    "latest_version": "1.0",
    "author": "the WordPress team",
    "template": "twentytwentyfour",
    "stylesheet": "twentytwentyfour"
  },
  "plugins": [
    {
      "name": "Akismet Anti-spam: Spam Protection",
      "version": "5.3",
      "active": true,
      "update_available": true,
      "latest_version": "5.3.1",
      "author": "Automattic - Anti-spam Team",
      "description": "Protect your site from spam in comments and contact forms.",
      "plugin_uri": "https://akismet.com/"
    },
    {
      "name": "Hello Dolly",
      "version": "1.7.2",
      "active": false,
      "update_available": false,
      "latest_version": "1.7.2",
      "author": "Matt Mullenweg",
      "description": "This is not just a plugin, it symbolizes the hope and enthusiasm...",
      "plugin_uri": "http://wordpress.org/plugins/hello-dolly/"
    }
  ],
  "timestamp": "2024-01-15 10:30:45",
  "timestamp_gmt": "2024-01-15 13:30:45"
}
```

### Respostas de Erro

#### 401 Unauthorized (Não autenticado)

```json
{
  "code": "rest_forbidden",
  "message": "Você precisa estar autenticado para acessar este endpoint.",
  "data": {
    "status": 401
  }
}
```

#### 403 Forbidden (Sem permissão)

```json
{
  "code": "rest_forbidden",
  "message": "Você não tem permissão para acessar este recurso.",
  "data": {
    "status": 403
  }
}
```

#### 500 Internal Server Error

```json
{
  "code": "system_info_error",
  "message": "Erro ao coletar informações do sistema: [detalhes do erro]",
  "data": {
    "status": 500
  }
}
```

## 🔍 Campos da Resposta

### Campos Principais

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `wordpress_version` | string | Versão instalada do WordPress |
| `php_version` | string | Versão do PHP do servidor |
| `theme` | object | Informações do tema ativo |
| `plugins` | array | Lista de todos os plugins instalados |
| `timestamp` | string | Data/hora da requisição (timezone do site) |
| `timestamp_gmt` | string | Data/hora da requisição (GMT/UTC) |

### Objeto Theme

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `name` | string | Nome do tema |
| `version` | string | Versão atual instalada |
| `update_available` | boolean | Se há atualização disponível |
| `latest_version` | string | Versão mais recente disponível |
| `author` | string | Autor do tema |
| `template` | string | Diretório do tema template |
| `stylesheet` | string | Diretório do tema stylesheet |

### Objeto Plugin (item do array plugins)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `name` | string | Nome do plugin |
| `version` | string | Versão atual instalada |
| `active` | boolean | Se o plugin está ativo |
| `update_available` | boolean | Se há atualização disponível |
| `latest_version` | string | Versão mais recente disponível |
| `author` | string | Autor do plugin |
| `description` | string | Descrição do plugin |
| `plugin_uri` | string | URL do site do plugin |

## 🛡️ Segurança

### Boas Práticas

1. **Use HTTPS**: Sempre utilize HTTPS em produção para proteger as credenciais
2. **Senhas de Aplicação**: Use Application Passwords em vez da senha principal
3. **Permissões Mínimas**: Crie usuários específicos com permissões mínimas necessárias
4. **Rotação de Senhas**: Revogue e recrie Application Passwords periodicamente
5. **Monitoramento**: Monitore logs de acesso ao endpoint

### Revogando Acesso

Para revogar o acesso de uma Application Password:

1. Acesse **Usuários > Perfil**
2. Na seção **Senhas de aplicação**, encontre a senha que deseja revogar
3. Clique em **Revogar**

## 🔧 Requisitos do Sistema

- **WordPress**: 5.6 ou superior
- **PHP**: 7.4 ou superior
- **Application Passwords**: Habilitado (padrão desde WP 5.6)

## 🐛 Troubleshooting

### Erro 401: Não autenticado

- Verifique se o usuário e a Application Password estão corretos
- Certifique-se de que está usando a Application Password, não a senha principal
- Verifique se o formato da autenticação está correto

### Erro 404: Endpoint não encontrado

- Verifique se o plugin está ativado
- Vá em **Configurações > Links permanentes** e clique em **Salvar** para flush das rewrite rules

### Application Passwords não aparece

- Certifique-se de que está usando WordPress 5.6 ou superior
- Verifique se seu site está usando HTTPS (obrigatório para Application Passwords)
- Em ambientes de desenvolvimento local, você pode forçar a habilitação adicionando ao `wp-config.php`:
  ```php
  define('WP_ENVIRONMENT_TYPE', 'local');
  ```

## 📝 Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

## 👨‍💻 Autor

**Bruno Albim**
- GitHub: [@brunoalbim](https://github.com/brunoalbim)

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou pull requests.

## 📚 Recursos Adicionais

- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [Application Passwords Documentation](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

## ⚠️ Notas Importantes

- Este plugin expõe informações sobre seu site WordPress. Use com cautela e apenas em ambientes confiáveis.
- Recomenda-se usar este plugin apenas para fins de monitoramento e administração interna.
- Considere implementar rate limiting adicional se necessário para prevenir abuso.

## 📋 Changelog

### Versão 1.0.0
- Release inicial
- Implementação do endpoint `/wp-json/wp-system/v1/info`
- Suporte a Application Passwords
- Informações de WordPress, PHP, tema e plugins
- Detecção de atualizações disponíveis
