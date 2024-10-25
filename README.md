# IMAP to SMTP Forwarder

This projects allows you to fetch messages via IMAP and forward these via SMTP to another E-Mail Address.
I've written this project to fetch messages from my IMAP server to forward these to several E-Mails based on the use case.
For example:

| Inbox         | Description                                                 | Targets                                                                                                          |
|---------------|-------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------|
| INBOX.flat    | All messages related to my flat are delivered to this INBOX | My personal inbox, my roommate                                                                                   |
| INBOX.netflix | All messages related to netflix are delivered to this INBOX | Forwarding message to my roommate, i.e. for business trips where that annoying temporary code has to be entered. |

So there might be other use cases, but this is for what I use this forwarder.

Whenever a mail is forwarded to one or more recipients, the original mail is forwarded as an attachment. So the actual mail is forwarded without any changes so that the mail can be opened by the recipient
as if he received the mail directly.

I played a lot with SRS (Sender Rewriting Scheme) in my old mail server but that introduced **a lot** of issues with several mail servers such as Apple iCloud, GMail, etc.
Therefore, I wrote this project to forward messages by using a real SMTP with a real sender address and real recipients and just attach the RAW mail to that mail.

You can either use a [catch all](https://en.wikipedia.org/wiki/Email_filtering) setup along with filters to move mails into the according folders before forwarding these or you can use so-called sub-folder addresses.
Usually, most of the mail servers accept something like `john+subfolder@doe.com` which would be delivered to the `john` account and - if there is a subfolder called `INBOX.subfolder` - into that folder. If the folder does not exist, it will be delivered to `INBOX` instead.

## Configuration

| Key                            | Type (Format)       | Required            | Description                                                                                                               | Example                                                                                                                                                                                                      |
|--------------------------------|---------------------|---------------------|---------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `addresses`                    | `object`            | Yes                 | Collection of addresses where each key is a name, containing `name` and `email`.                                          | ` { "john": { "name": "John Doe", "email": "john@doe.com" } }`                                                                                                                                               |
| `addresses.<key>.name`         | `string`            | No                  | Name of the person or sender.                                                                                             | `"John Doe"`                                                                                                                                                                                                 |
| `addresses.<key>.email`        | `string` (`email`)  | Yes                 | Email address of the person or sender.                                                                                    | `"john@doe.com"`                                                                                                                                                                                             |
| `templates`                    | `object`            | Yes                 | Defines templates for emails as arrays of strings, allowing for multi-line email bodies.                                  | ` { "default": ["Hello %recipient.name%,", "New message from %message.from.email%"] }`                                                                                                                       |
| `templates.<key>`              | `array` of `string` | Yes                 | Each entry represents a line in the email template. Uses placeholders like `%recipient.name%` and `%message.from.email%`. | ` [ "Hello %recipient.name%", "New message from %message.from.email%" ] `                                                                                                                                    |
| `imap`                         | `object`            | Yes                 | Collection of IMAP configurations, identified by unique keys.                                                             | ` { "gmail": { "hostname": "imap.gmail.com", "port": 993, "username": "john@doe.com", "password": "<plaintext password>" } }`                                                                                |
| `imap.<key>.hostname`          | `string`            | Yes                 | The hostname for the IMAP server.                                                                                         | `"imap.gmail.com"`                                                                                                                                                                                           |
| `imap.<key>.port`              | `integer`           | Yes                 | Port number for the IMAP server, typically `993` for secure connections.                                                  | `993`                                                                                                                                                                                                        |
| `imap.<key>.username`          | `string`            | Yes                 | Username for the IMAP server, usually an email address.                                                                   | `"john@doe.com"`                                                                                                                                                                                             |
| `imap.<key>.password`          | `string`            | Yes                 | Password for the IMAP server. This should be stored securely.                                                             | `"<plaintext password>"`                                                                                                                                                                                     |
| `smtp`                         | `object`            | Yes                 | Collection of SMTP configurations, identified by unique keys.                                                             | `{ "gmail": { "hostname": "smtp.gmail.com", "port": 465, "username": "john@doe.com", "password": "<plaintext password>" } }`                                                                                 |
| `smtp.<key>.hostname`          | `string`            | Yes                 | The hostname for the SMTP server.                                                                                         | `"smtp.gmail.com"`                                                                                                                                                                                           |
| `smtp.<key>.port`              | `integer`           | Yes                 | Port number for the SMTP server, typically `465` for secure connections.                                                  | `465`                                                                                                                                                                                                        |
| `smtp.<key>.username`          | `string`            | Yes                 | Username for the SMTP server, usually an email address.                                                                   | `"john@doe.com"`                                                                                                                                                                                             |
| `smtp.<key>.password`          | `string`            | Yes                 | Password for the SMTP server. This should be stored securely.                                                             | `"<plaintext password>"`                                                                                                                                                                                     |
| `forwards`                     | `array` of `object` | Yes                 | Array of forwarding rules. Each rule specifies how to handle incoming emails.                                             | `[ { "imap": "gmail", "smtp": { "identifier": "gmail", "sender": "netflix" }, "inbox": "INBOX.Netflix", "action": "move", "inboxToMove": "INBOX", "recipients": ["wade", "jane"], "template": "default" } ]` |
| `forwards.<index>.imap`        | `string`            | Yes                 | Identifier for the IMAP configuration to use.                                                                             | `"gmail"`                                                                                                                                                                                                    |
| `forwards.<index>.smtp`        | `object`            | Yes                 | SMTP configuration details for the forwarding rule.                                                                       | `{ "identifier": "gmail", "sender": "netflix" }`                                                                                                                                                             |
| `forwards.<index>.inbox`       | `string`            | No, default `INBOX` | The inbox from which emails will be forwarded.                                                                            | `"INBOX.Netflix"`                                                                                                                                                                                            |
| `forwards.<index>.action`      | `string`            | Yes                 | The action to take on the incoming email. Possible values are `"move"` or `"delete"`.                                     | `"move"`                                                                                                                                                                                                     |
| `forwards.<index>.inboxToMove` | `string`            | Conditionally       | The inbox to which emails will be moved (required if action is `move`).                                                   | `"INBOX"`                                                                                                                                                                                                    |
| `forwards.<index>.recipients`  | `array` of `string` | Yes                 | List of recipient identifiers to whom the email will be forwarded.                                                        | `[ "wade", "jane" ]`                                                                                                                                                                                         |
| `forwards.<index>.template`    | `string`            | Yes                 | The template to use for the forwarded email.                                                                              | `"default"`                                                                                                                                                                                                  |
| `forwards.<index>.markAsRead`  | `boolean`           | No, default `true`  | Flag whether the mail is marked as read after being moved. Only available with "move" action.                             | `true`                                                                                                                                                                                                       |

### Addresses

Addresses are a map containing the identifier of the address along with the "name" and the "email". 
So it contains both recipients and senders.

### Templates

You can configure several templates with identifiers. Since templates are arrays of strings, you can kinda create a full E-Mail with linebreaks, etc.

For example, the following example will create an E-Mail like this:

```text
Hello my friend,

I hope you are doing good.

I just wanted to let you know that you are awesome!
```

```json
{
  "templates": {
    "whatever": [
      "Hello my friend,",
      "",
      "I hope you are doing good.",
      "",
      "I just wanted to let you know that you are awesome!"
    ]
  }
}
```

There are several placeholders to be used in these templates:

| Placeholder            | Content                                                                                                  |
|------------------------|----------------------------------------------------------------------------------------------------------|
| `%recipient.name%`     | Recipients name, so basically the name from `addresses` - depending on the actual recipient of the mail. |
| `%message.from.name%`  | The name of the sender of the message we are forwarding.                                                 |
| `%message.from.email%` | The email of the sender of the message we are forwarding.                                                |
| `%message.to.name%`    | The original recipients name of the message we are forwarding.                                           |
| `%message.to.email%`   | The original recipients email of the message we are forwarding.                                          |

### IMAP

The IMAP configuration. You can configure a bunch of IMAP servers which can be used to fetch mails from.

### SMTP

The SMTP configuration. You can configure a bunch of SMTP servers which can be used to send mails with.

### Forwards

The actual "forward" configuration. This is used to bring everything together, i.e. which IMAP server to use to scrape mails from, 
what INBOX to use to see if there is an unprocessed mail, what recipients the mail should be forwarded to and the `template` to use
for the forwarded mail and finally what to do with these mails **AFTER FORWARDING** by configuring the `action`,
additionally you can configure the actual `inboxToMove` in case the message should be moved, i.e. to an archive folder or whatever, and
`markAsRead` so that the mail is not displayed as unread in the target folder.


### Example Configuration

```json
{
  "addresses": {
    "john": {
      "name": "John Doe",
      "email": "john@doe.com"
    },
    "netflix": {
      "name": "John Doe",
      "email": "netflix@doe.com"
    },
    "jane": {
      "name": "Jane doe",
      "email": "jane@doe.com"
    },
    "flat": {
      "name": "John Doe",
      "email": "flat@doe.com"
    },
    "wade": {
      "name": "Wade Doe",
      "email": "wade@doe.com"
    }
  },
  "templates": {
    "default": [
      "Hello %recipient.name%,",
      "",
      "a new message from %message.from.name% (%message.from.email%) was received by %message.to.name% (%message.to.email%).",
      "You'll find the original message attached to this mail. In case you want to reply to the original mail, please open the attached mail and reply to that mail instead.",
      "A reply to this E-Mail will not reach the original sender!"
    ]
  },
  "imap": {
    "gmail": {
      "hostname": "imap.gmail.com",
      "port": 993,
      "username": "john@doe.com",
      "password": "<plaintext password>"
    }
  },
  "smtp": {
    "gmail": {
      "hostname": "smtp.gmail.com",
      "port": 465,
      "username": "john@doe.com",
      "password": "<plaintext password>"
    }
  },
  "forwards": [
    {
      "imap": "gmail",
      "smtp": {
        "identifier": "gmail",
        "sender": "netflix"
      },
      "inbox": "INBOX.Netflix",
      "action": "move",
      "inboxToMove": "INBOX",
      "recipients": [
        "wade",
        "jane"
      ],
      "template": "default"
    },
    {
      "imap": "gmail",
      "smtp": {
        "identifier": "gmail",
        "sender": "flat"
      },
      "inbox": "INBOX.Flat",
      "action": "move",
      "inboxToMove": "INBOX",
      "recipients": [
        "jane",
        "wade"
      ],
      "template": "default"
    }
  ]
}
```