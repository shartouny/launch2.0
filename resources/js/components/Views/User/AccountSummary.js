import React, {Component} from 'react';
import {Button, message, Pagination, Table} from 'antd';
import axios from 'axios';

export default class AccountSummary extends Component {
  constructor(props) {
    super(props);
    this.state = {
      invoices: [],
      isLoading: true,
      buttonId: null,
      // Pagination State
      total: null,
      pageSize: null,
      currentPage: null,
      page: null
    }

    this.columns = [
      {
        title: 'Invoice #',
        dataIndex: 'id',
        key: 'id',
        render: value => value
      },
      {
        title: 'Period',
        dataIndex: 'period',
        key: 'period',
        render: value => value
      },
      {
        title: 'Amount',
        dataIndex: 'amount',
        key: 'amount',
        render: value => `$${value}`
      },
      {
        key: 'download',
        render: (_, record) => <Button type='primary'
              style={{width: '150px'}}
              key={record.id}
              loading={!!(this.state.buttonId === record.id && this.state.isLoading)}
              onClick={e => this.downloadPDF(e, record)}>Download</Button>
      }
    ]
  }

  getInvoices = (page = 1) => {
    axios.get(`/account/invoices?page=${page}`)
    .then(response => {
      const params = new URL(response.data.first_page_url).searchParams;
      const currentPage = Number(params.get('page'));
      const total = response.data.total;
      const pageSize = Number(response.data.per_page);
      const invoices = response.data.data.map(invoice => ({...invoice, key: invoice.id}));

      this.setState({
        invoices,
        currentPage,
        total,
        pageSize
      })
    })
    .catch(error => message.error(error))
  }

  downloadPDF = (e, record) => {
    e.preventDefault();
    this.setState({
      isLoading: true,
      buttonId: record.id
    })

    axios({
      method: 'POST',
      data: {
        date: record.period,
        invoiceNumber: record.id
      },
      url: `/account/download`,
      responseType: 'blob'

    }).then(response => {
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `invoice-${record.id}.pdf`);
      document.body.appendChild(link);
      link.click();

    }).catch(error => {
      message.error('Something went wrong when trying to download the invoice')
      console.log(error)

    }).finally(() => this.setState({
      isLoading: false,
      buttonId: null
    }))
  }

  shouldShowPagination = () => this.state.invoices.length > this.state.pageSize;

  componentDidMount() {
    this.getInvoices();
  }

  render() {
    const {
      invoices,
      pageSize,
      total,
      currentPage
    } = this.state;

    return (
      <>
        <Table dataSource={invoices}
          pagination={false}
          columns={this.columns}  />
        {this.shouldShowPagination() && <Pagination pageSize={pageSize}
          total={total}
          onChange={ page => this.getInvoices(page) }
          current={currentPage} />}
      </>
    )
  }
}
