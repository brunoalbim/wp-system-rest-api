# WP System REST API

Plugin WordPress que expõe informações do sistema via REST API protegida por autenticação.

## 📋 Descrição

Expõe informações do sistema WordPress via REST API protegida por autenticação. Inclui integração com UpdraftPlus para visibilidade de backups.

- Versão do WordPress
- Versão do PHP
- Tema ativo (com informações de atualização)
- Lista completa de plugins instalados (ativos e inativos)
- Status de atualizações disponíveis
- Dados de backups do Updraft

## ✨ Características

- **Seguro**: Protegido por autenticação usando Application Passwords (Senhas de Aplicação)
- **Completo**: Retorna todos os dados em um único payload JSON
- **Informativo**: Inclui informações sobre atualizações disponíveis
- **Simples**: Implementação direta e fácil de usar
- **Robusto**: Tratamento de erros adequado e código bem documentado

## 🚀 Instalação

### Método 1: Upload Manual

1. Baixe o arquivo "wp-system-rest-api.zip" mais recente em [https://github.com/brunoalbim/wp-system-rest-api/releases](https://github.com/brunoalbim/wp-system-rest-api/releases)
2. Acesse o painel administrativo do WordPress
3. Vá em **Plugins > Adicionar novo > Fazer upload do plugin**
4. Selecione o arquivo e clique em **Instalar agora**
5. Ative o plugin


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

### Endpoint info do sistema

```
GET /wp-json/wp-system/v1/info
```

### Endpoint Updraft

```
GET /wp-json/wp-system/v1/backup
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
  "wordpress_version": {
    "version": "6.4.2",
    "update_available": false,
    "latest_version": "6.4.2"
  },
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
| `wordpress_version` | object | Informações da versão do WordPress |
| `php_version` | string | Versão do PHP do servidor |
| `theme` | object | Informações do tema ativo |
| `plugins` | array | Lista de todos os plugins instalados |
| `timestamp` | string | Data/hora da requisição (timezone do sit| `timestamp_gmt` | string | Data/hora da requisição (GMT/UTC) |

### Objeto WordPress Version

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `version` | string | Versão atual instalada do WordPress |
| `update_available` | boolean | Se há atualização disponível |
| `latest_version` | string | Versão mais recente disponível |

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

## 🔄 Atualizações

O plugin verifica automaticamente por novas versões usando as [Releases do GitHub](https://github.com/brunoalbim/wp-system-rest-api/releases) como fonte, via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker). Quando uma nova versão é publicada, ela aparece na tela **Plugins** do wp-admin como qualquer outra atualização, com botão **Atualizar agora**.

### Como publicar uma nova versão (para mantenedores)

A criação da tag e da release é automática — basta subir a versão no código:

1. Atualize o campo `Version:` no header de `wp-system-rest-api.php`.
2. Faça commit da alteração e dê push na `main`.
3. O workflow `.github/workflows/release.yml` detecta que o `Version:` mudou, cria e envia a tag `vX.Y.Z` correspondente, monta o `.zip` com a estrutura correta e publica a GitHub Release com o pacote anexado — tudo no mesmo job.
4. Sites com o plugin instalado passarão a ver a atualização no wp-admin (o WordPress verifica periodicamente; para forçar, use "Verificar novamente" na tela de Plugins/Updates).

Não é necessário criar ou enviar a tag manualmente — apenas garanta que o `Version:` do header esteja correto antes do commit.

## 📝 Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

## 👨‍💻 Autor

**Bruno Albim**
- GitHub: [@brunoalbim](https://github.com/brunoalbim)

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para abrir issues ou pull requests.

- Implementação do endpoint `/wp-json/wp-system/v1/info`
- Suporte a Application Passwords
- Informações de WordPress, PHP, tema e plugins
- Detecção de atualizações disponíveis para temas e plugins
