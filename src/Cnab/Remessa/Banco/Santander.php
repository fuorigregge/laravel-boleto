<?php
/**
 *   Copyright (c) 2016 Eduardo Gusmão
 *
 *   Permission is hereby granted, free of charge, to any person obtaining a
 *   copy of this software and associated documentation files (the "Software"),
 *   to deal in the Software without restriction, including without limitation
 *   the rights to use, copy, modify, merge, publish, distribute, sublicense,
 *   and/or sell copies of the Software, and to permit persons to whom the
 *   Software is furnished to do so, subject to the following conditions:
 *
 *   The above copyright notice and this permission notice shall be included in all
 *   copies or substantial portions of the Software.
 *
 *   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 *   INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 *   PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 *   COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 *   WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
 *   IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Eduardokum\LaravelBoleto\Cnab\Remessa\Banco;

use Eduardokum\LaravelBoleto\Cnab\Remessa\AbstractRemessa;
use Eduardokum\LaravelBoleto\Contracts\Cnab\Remessa as RemessaContract;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Util;

class Santander extends AbstractRemessa implements RemessaContract
{
    const ESPECIE_DUPLICATA = '01';
    const ESPECIE_NOTA_PROMISSORIA = '02';
    const ESPECIE_NOTA_SEGURO = '03';
    const ESPECIE_RECIBO = '05';
    const ESPECIE_DUPLICATA_SERVICO = '06';
    const ESPECIE_LETRA_CAMBIO = '07';

    const OCORRENCIA_REMESSA = '01';
    const OCORRENCIA_BAIXA_TITULO = '02';
    const OCORRENCIA_CONCESSAO_ABATIMENTO = '04';
    const OCORRENCIA_CANC_ABATIMENTO = '05';
    const OCORRENCIA_ALT_VENCIMENTO = '06';
    const OCORRENCIA_ALT_CONTROLE_PARTICIPANTE = '07';
    const OCORRENCIA_ALT_SEUNUMERO = '08';
    const OCORRENCIA_PROTESTAR = '09';
    const OCORRENCIA_SUSTAR_PROTESTO = '18';

    const INSTRUCAO_SEM = '00';
    const INSTRUCAO_BAIXAR_APOS_VENC_15 = '02';
    const INSTRUCAO_BAIXAR_APOS_VENC_30 = '03';
    const INSTRUCAO_NAP_AIXAR = '04';
    const INSTRUCAO_PROTESTAR = '06';
    const INSTRUCAO_NAO_PROTESTAR = '07';
    const INSTRUCAO_NAO_COBRAR_MORA = '08';

    /**
     * Código do banco
     * @var string
     */
    protected $codigoBanco = self::COD_BANCO_SANTANDER;

    /**
     * Define as carteiras disponíveis para cada banco
     * @var array
     */
    protected $carteiras = [101,201];

    /**
     * Caracter de fim de linha
     *
     * @var string
     */
    protected $fimLinha = "\r\n";

    /**
     * Caracter de fim de arquivo
     *
     * @var null
     */
    protected $fimArquivo = "\r\n";

    /**
     * Codigo do cliente junto ao banco.
     *
     * @var string
     */
    protected $codigoCliente;

    /**
     * Retorna o codigo do cliente.
     *
     * @return mixed
     */
    public function getCodigoCliente()
    {
        return $this->codigoCliente;
    }

    /**
     * Seta o codigo do cliente.
     *
     * @param mixed $codigoCliente
     *
     * @return Bradesco
     */
    public function setCodigoCliente($codigoCliente)
    {
        $this->codigoCliente = $codigoCliente;

        return $this;
    }

    /**
     * Retorna o codigo de transmissão.
     *
     * @return mixed
     */
    public function getCodigoTransmissao()
    {
        return Util::formatCnab('9', $this->getAgencia(), 4)
            . Util::formatCnab('9', $this->getCodigoCliente(), 8)
            . Util::formatCnab('9', $this->getConta(), 8);
    }

    /**
     * Valor total dos titulos.
     *
     * @var float
     */
    private $total = 0;

    protected function header()
    {
        $this->iniciaHeader();

        $this->add(1, 1, '0' );
        $this->add(2, 2, '1');
        $this->add(3, 9, 'REMESSA');
        $this->add(10, 11, '01');
        $this->add(12, 26, Util::formatCnab('X', 'COBRANCA', 15));
        $this->add(27, 46, Util::formatCnab('9', $this->getCodigoTransmissao(), 20));
        $this->add(47, 76, Util::formatCnab('X', $this->getBeneficiario()->getNome(), 30));
        $this->add(77, 79, $this->getCodigoBanco());
        $this->add(80, 94, Util::formatCnab('X', 'SANTANDER', 15));
        $this->add(95, 100, date('dmy'));
        $this->add(101, 116, Util::formatCnab('9','0',16));
        $this->add(117, 391, '');
        $this->add(392, 394, '000');
        $this->add(395, 400, Util::formatCnab('9', 1, 6));

        return $this;
    }

    public function addBoleto(BoletoContract $boleto)
    {
        $this->iniciaDetalhe();

        $this->total += $boleto->getValor();

        $this->add(1, 1, '1');
        $this->add(2, 3, strlen(Util::onlyNumbers($this->getBeneficiario()->getDocumento())) == 14 ? '02' : '01');
        $this->add(4, 17, Util::formatCnab('9L', $this->getBeneficiario()->getDocumento(), 14));
        $this->add(18, 37, Util::formatCnab('9', $this->getCodigoTransmissao(), 20));
        $this->add(38, 62, Util::formatCnab('X', '', 25)); // numero de controle
        $this->add(63, 70, Util::numberFormatGeral($boleto->getNumeroDocumento(), 7) . Util::modulo11($boleto->getNumeroDocumento()));
        $this->add(71, 76, '000000');
        $this->add(77, 77, '');
        $this->add(78, 78, ($boleto->getMulta() !== false ? '4' : '0'));
        $this->add(79, 82, Util::formatCnab('9', $boleto->getMulta(), 4, 2));
        $this->add(83, 84, '00');
        $this->add(85, 97, Util::formatCnab('9', 0, 13, 2));
        $this->add(98, 101, Util::formatCnab('X', '', 4));
        $this->add(102, 107, $boleto->getDataVencimento()->copy()->addDays($boleto->getJurosApos(0))->format('dmy'));
        $this->add(108, 108, $this->getCarteiraNumero() > 200 ? '1' : '5');
        $this->add(109, 110, '01'); // REGISTRO
        if($boleto->getStatus() == $boleto::STATUS_BAIXA)
        {
            $this->add(109, 110, '02'); // BAIXA
        }
        if($boleto->getStatus() == $boleto::STATUS_ALTERACAO)
        {
            $this->add(109, 110, '06'); // ALTERAR VENCIMENTO
        }
        $this->add(111, 120, Util::formatCnab('X', $boleto->getNumeroDocumento(), 10));
        $this->add(121, 126, $boleto->getDataVencimento()->format('dmy'));
        $this->add(127, 139, Util::formatCnab('9', $boleto->getValor(), 13, 2));
        $this->add(140, 142, $this->getCodigoBanco());
        $this->add(143, 147, '00000');
        $this->add(148, 149, $boleto->getEspecieDocCodigo());
        $this->add(150, 150, $boleto->getAceite());
        $this->add(151, 156, $boleto->getDataDocumento()->format('dmy'));

        $this->add(157, 158, '00');
        $this->add(159, 160, '00');

        if($boleto->getDiasProtesto() !== false)
        {
            $this->add(157, 158, '06');
        }

        $juros = 0;
        if($boleto->getJuros() !== false)
        {
            $juros = Util::percent($boleto->getValor(), $boleto->getJuros())/30;
        }
        $this->add(161, 173, Util::formatCnab('9', $juros, 13, 2));
        $this->add(174, 179, '000000');
        $this->add(180, 192, Util::formatCnab('9', 0, 13, 2));
        $this->add(193, 205, Util::formatCnab('9', 0, 13, 2));
        $this->add(206, 218, Util::formatCnab('9', $boleto->getDescontosAbatimentos(), 13, 2));
        $this->add(219, 220, strlen(Util::onlyNumbers($boleto->getPagador()->getDocumento())) == 14 ? '02' : '01');
        $this->add(221, 234, Util::formatCnab('9L', $boleto->getPagador()->getDocumento(), 14));
        $this->add(235, 274, Util::formatCnab('X', $boleto->getPagador()->getNome(), 40));
        $this->add(275, 314, Util::formatCnab('X', $boleto->getPagador()->getEndereco(), 40));
        $this->add(315, 326, Util::formatCnab('X', $boleto->getPagador()->getBairro(), 12));
        $this->add(327, 334, Util::formatCnab('9L', $boleto->getPagador()->getCep(), 8));
        $this->add(335, 349, Util::formatCnab('X', $boleto->getPagador()->getCidade(), 15));
        $this->add(350, 351, Util::formatCnab('X', $boleto->getPagador()->getUf(), 2));
        $this->add(352, 381, Util::formatCnab('X', $boleto->getSacadorAvalista() ? $boleto->getSacadorAvalista()->getNome() : '', 30));
        $this->add(382, 382, '');
        $this->add(383, 383, 'I');
        $this->add(384, 385, substr($this->getConta(),-2));
        $this->add(386, 391, Util::formatCnab('N', '', 6));
        $this->add(392, 393, Util::formatCnab('9', $boleto->getDiasProtesto('0'), 2));
        $this->add(394, 394, '');
        $this->add(395, 400, Util::formatCnab('N', $this->iRegistros+1, 6));

        return $this;
    }

    protected function trailer()
    {
        $this->iniciaTrailer();

        $this->add(1, 1, '9');
        $this->add(2, 7, Util::formatCnab('9', $this->getCount(), 6));
        $this->add(8, 20, Util::formatCnab('9', $this->total, 13, 2));
        $this->add(21, 394, Util::formatCnab('9', 0, 374));
        $this->add(395, 400, Util::formatCnab('9', $this->getCount(), 6));

        return $this;
    }

    public function isValid()
    {
        $codigoCliente = $this->getCodigoCliente();
        if(empty($codigoCliente) || !parent::isValid())
        {
            return false;
        }

        return true;
    }

}
