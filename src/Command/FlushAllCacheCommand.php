<?php
declare(strict_types=1);

namespace Plaisio\Cache\Command;

use Plaisio\Cache\Event\FlushAllCacheEvent;
use Plaisio\CompanyResolver\UniCompanyResolver;
use Plaisio\Console\Command\PlaisioCommand;
use Plaisio\Console\Command\PlaisioKernelCommand;
use SetBased\Helper\Cast;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command for flushing all caches.
 */
class FlushAllCacheCommand extends PlaisioCommand
{
  //--------------------------------------------------------------------------------------------------------------------
  use PlaisioKernelCommand;

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * @inheritdoc
   */
  protected function configure()
  {
    $this->setName('plaisio:cache-flush-all')
         ->setDescription('Flushes all caches for a company or all companies')
         ->addArgument('company', InputArgument::OPTIONAL, 'The abbreviation of the company')
         ->addOption('all', 'a', InputOption::VALUE_NONE, 'All companies');
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Executes this command.
   *
   * @param InputInterface  $input  The input object.
   * @param OutputInterface $output The output object.
   *
   * @return int
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $company = Cast::toOptString($input->getArgument('company'));
    $all     = Cast::toManBool($input->getOption('all'));

    if (($company===null && $all===false) || ($company!==null && $all===true))
    {
      $helper = new DescriptorHelper();
      $helper->describe($output, $this, []);

      return 0;
    }

    if (!$this->nub->DL->isAlive())
    {
      $this->nub->DL->connect();
    }

    if ($all)
    {
      $this->forAllCompanies();
    }

    if ($company!==null)
    {
      $cmpId = $this->nub->DL->abcCompanyGetCmpIdByCmpAbbr($company);
      if ($cmpId===null)
      {
        $this->io->error(sprintf("Unknown company '%s'.", $company));

        return -1;
      }

      $this->forCompany($cmpId);
    }

    return 0;
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Flushes all caches for all companies.
   */
  private function forAllCompanies(): void
  {
    $companies = $this->nub->DL->abcCompanyGetAll();
    foreach ($companies as $company)
    {
      $this->forCompany($company['cmp_id']);
    }
  }

  //--------------------------------------------------------------------------------------------------------------------
  /**
   * Flushes all caches for a company.
   *
   * @param int $cmpId The ID of the company.
   */
  private function forCompany(int $cmpId): void
  {
    $this->nub->company = new UniCompanyResolver($cmpId);

    $this->nub->eventDispatcher->notify(new FlushAllCacheEvent());
    $this->nub->eventDispatcher->dispatch();
    $this->nub->DL->commit();
  }

  //--------------------------------------------------------------------------------------------------------------------
}

//----------------------------------------------------------------------------------------------------------------------
