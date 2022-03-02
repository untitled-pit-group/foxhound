#!/usr/bin/env ruby

require 'irb'
require 'net/http'
require 'json'

class Net::HTTPResponse
  def ok?
    (200...300).include? code.to_i
  end
end

class ResponseError < ::RuntimeError
end
class ServerError < ::RuntimeError
  def initialize(error)
    super("#{error['code']}: #{error['message']}")
    set_backtrace Thread.current.backtrace[1..]
    @server_exception = error['data']
  end

  def to_s
    msg = super
    unless @server_exception.nil?
      e = @server_exception
      msg += "\nRemote: #{e['exception']}: #{e['message']}\n"
      msg += "    at #{e['location']}\n"
      msg += e['trace'].split("\n").collect { "    #{_1}" }.join("\n")
    end
    msg
  end
end

class MockClient
  def initialize
    @http = Net::HTTP.new('127.0.0.1', 8080)
    @secret = load_secret!
    @token = nil
  end

  def load_secret!
    # TODO
    nil
  end

  def mint_token!
    request = Net::HTTP::Post.new '/auth-token'
    request['Content-Type'] = 'text/plain'
    request.body = @secret

    response = @http.request request
    raise ResponseError, "#{response.code}: #{response.body}" unless response.ok?
    @token = response.body.rstrip
  end

  def new_rpc_request(method, params)
    request = Net::HTTP::Post.new '/rpc'
    #request['Authorization'] = "Bearer #{@token}"
    request['Accept'] = 'application/json'
    request['Content-Type'] = 'application/json; charset=UTF-8'
    request.body = {
      :jsonrpc => "2.0",
      :id => "Yahaha! You found me!",
      :method => method,
      :params => params,
    }.to_json
    request
  end

  def parse_response(response)
    return nil if response.code.to_i == 204
    if response['Content-Type'].start_with?('application/json')
      begin
        data = JSON.parse response.body
      rescue
        raise ResponseError, "Bad JSON: #{response.body}"
      end
      raise ServerError.new(data['error']) if data.has_key? 'error'
      data['result']
    else
      raise ResponseError, "#{response.code}: #{response.body}"
    end
  end

  def call(method, **params)
    #mint_token! if @token.nil?
    request = new_rpc_request(method, params)
    response = @http.request request
    parse_response(response)
  end

  def shell!
    IRB.setup(nil, argv: [])
    workspace = IRB::WorkSpace.new(self)
    IRB::Irb.new(workspace).run(IRB.conf)
  end

  def to_s
    "fxhd-client"
  end
end

MockClient.new.shell!
