<?php

namespace App\Command;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Dotclear2Hugo extends Command
{
    private $client;

    public function __construct(HttpClientInterface $client, string $name = null)
    {
        parent::__construct($name);
        $this->client = $client;
    }

    protected function configure()
    {
        $this
            ->setName('dotclear2hugo')
            ->setDescription('Dotclear2hugo command')
            ->addOption(
                'feed',
                '-f',
                InputOption::VALUE_REQUIRED,
                'Feed url'
            )
        ;
    }

    public function getItems($feed)
    {
        $response = $this->client->request('GET', $feed);
        $xml = simplexml_load_string($response->getContent());
        $data = [];
        $slugger = new AsciiSlugger();
        foreach ($xml->entry as $entry) {
            $item['title'] = (string) $entry->title;
            $item['slug'] = $slugger->slug($item['title']);
            $item['content'] = (string) $entry->content;
            $item['author'] = (string) $entry->author->name;
            $item['published'] = new \DateTime((string) $entry->published);
            $item['lastModified'] = new \DateTime((string) $entry->updated);
            $item['link'] = (string) $entry->link['href'];
            $item['tags'] = [];
            $dc = $entry->children('dc', TRUE);
            foreach($dc->subject as $tag) {
                $item['tags'][] = (string) $tag;
            }
            $data[] = $item;
        }
        return $data;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $feed = $input->getOption('feed');

        foreach($this->getItems($feed) as $item ) {
            $fullDestPath = './content/post/' . $item['slug'] . '.md';
            $this->createItem($fullDestPath, $item);
        }

        $output->writeln($feed);
        return 0;
    }

    protected function createItem($fullDestPath, array $item)
    {
        preg_match('/https?:\/\/[a-z.]+/', $item['link'], $matches);
        $baseUrl = $matches[0];
        $alias = substr($item['link'], strlen($baseUrl));
        $contenttype = "post";
        $file = new Filesystem();
        if ($file->exists($fullDestPath)) {
            $file->remove($fullDestPath);
        }
        $file->appendToFile($fullDestPath, '---' . PHP_EOL);
        $file->appendToFile($fullDestPath, sprintf('date: %s' . PHP_EOL, $item['published']->format('c')));
        $file->appendToFile($fullDestPath, sprintf('title: "%s"' . PHP_EOL, str_replace('"', '\"', $item['title'])));
        $file->appendToFile($fullDestPath, sprintf('author: "%s"' . PHP_EOL, $item['author']));
        $file->appendToFile($fullDestPath, sprintf('aliases: [%s]' . PHP_EOL, $alias));
        if (count($item['tags']) > 0) {
            $file->appendToFile($fullDestPath, sprintf('tags: ["%s"]' . PHP_EOL, implode('", "', $item['tags'])));
        }
        $file->appendToFile($fullDestPath, sprintf('lastmod: %s' . PHP_EOL, $item['lastModified']->format('c')));
        #$file->appendToFile($fullDestPath, sprintf('type: %s' . PHP_EOL, $contenttype));
        $file->appendToFile($fullDestPath, '---' . PHP_EOL);

        $converter = new HtmlConverter();
        $file->appendToFile($fullDestPath, $converter->convert($item['content']) . PHP_EOL);
    }
}