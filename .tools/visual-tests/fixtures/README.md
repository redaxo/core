<br/>
<div align="center">
    <a href="https://github.com/github_username/repo_name">
        <img src="https://raw.githubusercontent.com/redaxo/redaxo/assets/redaxo-logo.png" alt="REDAXO" width="280px" height="43px">
    </a>

<h1>Project title</h1>

<p align="center">
    project_description
    <br />
    <a href="https://github.com/github_username/repo_name"><strong>Explore the docs »</strong></a>
    <br />
    <br />
    <a href="https://github.com/github_username/repo_name">View Demo</a>
    ·
    <a href="https://github.com/github_username/repo_name/issues">Report Bug</a>
    ·
    <a href="https://github.com/github_username/repo_name/issues">Request Feature</a>
</p>
</div>

--------

## About The Project

[![Product Name Screen Shot][product-screenshot]](https://example.com)

Here's a blank template to get started: To avoid retyping too much info. Do a search and replace with your text editor for the following: `github_username`, `repo_name`, `project_title`, `project_description`

### Built With

* [Next.js](https://nextjs.org/)
* [React.js](https://reactjs.org/)
* [Vue.js](https://vuejs.org/)
* [Angular](https://angular.io/)
* [Svelte](https://svelte.dev/)
* [Laravel](https://laravel.com)
* [Bootstrap](https://getbootstrap.com)
* [JQuery](https://jquery.com)

## Getting Started

This is an example of how you may give instructions on setting up your project locally.
To get a local copy up and running follow these simple example steps.

### Prerequisites

This is an example of how to list things you need to use the software and how to install them.
* npm
  ```sh
  npm install npm@latest -g
  ```

### Installation

1. Get a free API Key at [https://example.com](https://example.com)
2. Clone the repo
   ```sh
   git clone https://github.com/github_username/repo_name.git
   ```
3. Install NPM packages
   ```sh
   npm install
   ```
4. Enter your API in `config.js`
   ```js
   const API_KEY = 'ENTER YOUR API';
   ```

## Usage

Use this space to show useful examples of how a project can be used. Additional screenshots, code examples and demos work well in this space. You may also link to more resources.

```html
<!DOCTYPE html>
<html>
<body>

    <h1>REDAXO</h1>

    <?php echo "The Best CMS"; ?>

</body>
</html> 
```

```php
<?php

class rex_test extends TestCase
{
    public function testSqlQuery()
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SELECT `column_r`, `column_e`, `column_d`, `column_a`, `column_x`, `column_o`,  FROM '.self::TABLE.' WHERE column_r = ?', [5]);
    }
}
```

_For more examples, please refer to the [Documentation](https://example.com)_

## Roadmap

- [ ] Feature 1
- [ ] Feature 2
- [x] Feature 3
    - [ ] Nested Feature

See the [open issues](https://github.com/github_username/repo_name/issues) for a full list of proposed features (and known issues).

## Contributing

Contributions are what make the open source community such an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

If you have a suggestion that would make this better, please fork the repo and create a pull request. You can also simply open an issue with the tag "enhancement".
Don't forget to give the project a star! Thanks again!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Markdown parse test

### Text

It's very easy to make some words **bold** and other words *italic* with Markdown. You can even [link to REDAXO!](https://redaxo.org)

### Lists

Sometimes you want numbered lists:

1. One
2. Two
3. Three

Sometimes you want bullet points:

* Start a line with a star
* Profit!

Alternatively,

- Dashes work just as well
- And if you have sub points, put two spaces before the dash or star:
  - Like this
  - And this

### Images

If you want to embed images, this is how you do it:

![Logo of REDAXO](https://raw.githubusercontent.com/redaxo/redaxo/assets/redaxo-logo.png)


### Quotes

> Coffee. The finest organic suspension ever devised... I beat the Borg with it.
> - Captain Janeway


### Code

There are many different ways to style code with GitHub's markdown. If you have inline code blocks, wrap them in backticks: `var example = true`.  If you've got a longer block of code, you can indent with four spaces:

    if (isAwesome){
      return true
    }

GitHub also supports something called code fencing, which allows for multiple lines without indentation:

```
if (isAwesome) {
  return true
}
```

And if you'd like to use syntax highlighting, include the language:

```js
if (isAwesome) {
  return true
}
```


### Tables

| header 1 | header 2 |
| -------- | -------- |
| cell 1.1 | cell 1.2 |
| cell 2.1 | cell 2.2 |


| header 1 | header 2 | header 2 |
| :------- | :------: | -------: |
| cell 1.1 | cell 1.2 | cell 1.3 |
| cell 2.1 | cell 2.2 | cell 2.3 |


[product-screenshot]: https://raw.githubusercontent.com/redaxo/redaxo/assets/redaxo_02.png
